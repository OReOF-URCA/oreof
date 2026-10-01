<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Faq;
use App\Entity\Help;
use App\Entity\HelpImage;
use App\Repository\FaqRepository;
use App\Repository\HelpImageRepository;
use App\Repository\HelpRepository;
use App\Service\HelpTransferService;
use App\Service\SecureUploadService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

class HelpTransferServiceTest extends TestCase
{
    private const IMAGE = '20260616_093244_afcd34b70fd7.png';
    private const PNG_1PX = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
    private const MARKDOWN = "Intro **gras**\n\n---\n\n# Sommaire\n\n<details>\n<summary><strong>Titre</strong></summary>\n\n![img](/uploads/help_images/" . self::IMAGE . ")\n\n</details>";

    private Filesystem $filesystem;
    private string $workDir;
    /** @var string[] */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->workDir = sys_get_temp_dir() . '/help_transfer_test_' . bin2hex(random_bytes(4));
        $this->filesystem->mkdir([$this->workDir . '/source', $this->workDir . '/target']);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove(array_merge([$this->workDir], $this->tempFiles));
    }

    public function testExportThenImportKeepsMarkdownNewlinesAndImages(): void
    {
        $archive = $this->exportSample();

        $persisted = [];
        $result = $this->createService('target', persisted: $persisted)->import($archive);

        self::assertSame([1, 1, 1], [$result->helpsCreated, $result->faqsCreated, $result->imagesCreated]);
        self::assertSame([], $result->warnings);

        $help = $this->firstOf($persisted, Help::class);
        self::assertSame('app_help_index', $help->getRouteSlug());
        self::assertSame('Page gestion des aides', $help->getTitle());
        self::assertSame(self::MARKDOWN, $help->getContent());
        self::assertStringNotContainsString('\n', (string)$help->getContent());
        self::assertSame(['cg_composante'], $help->getCentresShow());
        self::assertTrue($help->isActive());

        $faq = $this->firstOf($persisted, Faq::class);
        self::assertSame('Comment mutualiser une UE ?', $faq->getQuestion());
        self::assertSame("Ligne 1\n\nLigne 2", $faq->getReponse());
        self::assertSame(3, $faq->getOrdre());
        self::assertFalse($faq->isActive());

        $image = $this->firstOf($persisted, HelpImage::class);
        self::assertSame('galerie_image_bouton', $image->getNom());
        self::assertSame(self::IMAGE, $image->getFichier());
        self::assertFileEquals($this->workDir . '/source/' . self::IMAGE, $this->workDir . '/target/' . self::IMAGE);
    }

    public function testExportedArchiveContainsReadableMarkdownFiles(): void
    {
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($this->exportSample()));

        self::assertSame(self::MARKDOWN, $zip->getFromName('helps/001-app_help_index.md'));
        self::assertNotFalse($zip->statName('images/' . self::IMAGE));
        $manifest = json_decode((string)$zip->getFromName('manifest.json'), true, 32, JSON_THROW_ON_ERROR);
        self::assertSame(HelpTransferService::FORMAT, $manifest['format']);
        $zip->close();
    }

    public function testExportWithoutImagesOmitsGallery(): void
    {
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($this->exportSample(withImages: false)));

        self::assertFalse($zip->statName('images/' . self::IMAGE));
        self::assertSame([], json_decode((string)$zip->getFromName('manifest.json'), true)['images']);
        $zip->close();
    }

    public function testImportSkipsExistingEntriesUnlessOverwriteIsRequested(): void
    {
        $archive = $this->exportSample();
        $existingHelp = (new Help())->setRouteSlug('app_help_index')->setTitle('Ancien')->setContent('Ancien\n\ncontenu')->setIsActive(false);
        $existingFaq = (new Faq())->setQuestion('Comment mutualiser une UE ?')->setReponse('Ancienne');

        $persisted = [];
        $result = $this->createService('target', [$existingHelp], [$existingFaq], persisted: $persisted)->import($archive, false);

        self::assertSame([0, 0, 1], [$result->helpsCreated, $result->helpsUpdated, $result->helpsSkipped]);
        self::assertSame([0, 0, 1], [$result->faqsCreated, $result->faqsUpdated, $result->faqsSkipped]);
        self::assertSame('Ancien\n\ncontenu', $existingHelp->getContent());

        $result = $this->createService('target', [$existingHelp], [$existingFaq])->import($archive, true);

        self::assertSame([0, 1, 0], [$result->helpsCreated, $result->helpsUpdated, $result->helpsSkipped]);
        self::assertSame([0, 1, 0], [$result->faqsCreated, $result->faqsUpdated, $result->faqsSkipped]);
        self::assertSame(self::MARKDOWN, $existingHelp->getContent());
        self::assertSame('Page gestion des aides', $existingHelp->getTitle());
        self::assertTrue($existingHelp->isActive());
        self::assertSame("Ligne 1\n\nLigne 2", $existingFaq->getReponse());
    }

    public function testImportRestoresMissingFileOfAnAlreadyKnownImage(): void
    {
        $archive = $this->exportSample();
        $known = (new HelpImage())->setNom('galerie_image_bouton')->setFichier(self::IMAGE);

        $persisted = [];
        $result = $this->createService('target', images: [$known], persisted: $persisted)->import($archive);

        self::assertSame([0, 1, 0], [$result->imagesCreated, $result->imagesUpdated, $result->imagesSkipped]);
        self::assertFileExists($this->workDir . '/target/' . self::IMAGE);
        self::assertNull($this->firstOf($persisted, HelpImage::class, false));
    }

    public function testImportRejectsDisguisedImageAndUnknownRoute(): void
    {
        $archive = $this->buildArchive([
            'helps' => [['routeSlug' => 'route_inconnue', 'title' => 'T', 'isActive' => true, 'centresShow' => ['pirate'], 'file' => 'helps/001.md']],
            'images' => [
                ['nom' => 'faux', 'fichier' => self::IMAGE],
                ['nom' => 'faux en double', 'fichier' => self::IMAGE],
                ['nom' => 'traversee', 'fichier' => '../../evil.png'],
            ],
        ], ['helps/001.md' => 'Contenu', 'images/' . self::IMAGE => '<?php echo "pas une image";']);

        $persisted = [];
        $result = $this->createService('target', persisted: $persisted)->import($archive);

        self::assertSame(0, $result->imagesCreated);
        self::assertFileDoesNotExist($this->workDir . '/target/' . self::IMAGE);
        self::assertSame(1, $result->helpsCreated);
        self::assertSame([], $this->firstOf($persisted, Help::class)->getCentresShow());
        self::assertCount(3, $result->warnings);
    }

    public function testImportRejectsArchiveThatIsNotAnExport(): void
    {
        $notAnExport = $this->buildArchive(null, ['readme.txt' => 'bonjour']);
        $service = $this->createService('target');

        try {
            $service->import($notAnExport);
            self::fail('Une archive sans manifest doit être refusée.');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('manifest.json', $e->getMessage());
        }

        $notAZip = $this->tempFile();
        file_put_contents($notAZip, 'pas un zip');

        $this->expectException(\InvalidArgumentException::class);
        $service->import($notAZip);
    }

    private function exportSample(bool $withImages = true): string
    {
        file_put_contents($this->workDir . '/source/' . self::IMAGE, base64_decode(self::PNG_1PX));

        $help = (new Help())
            ->setRouteSlug('app_help_index')
            ->setTitle('Page gestion des aides')
            ->setContent(str_replace("\n", "\r\n", self::MARKDOWN))
            ->setIsActive(true)
            ->setCentresShow(['cg_composante']);
        $faq = (new Faq())
            ->setQuestion('Comment mutualiser une UE ?')
            ->setReponse("Ligne 1\n\nLigne 2")
            ->setIsActive(false)
            ->setOrdre(3);
        $image = (new HelpImage())->setNom('galerie_image_bouton')->setFichier(self::IMAGE);

        return $this->tempFiles[] = $this->createService('source', [$help], [$faq], [$image])->export($withImages);
    }

    /**
     * @param Help[]      $helps
     * @param Faq[]       $faqs
     * @param HelpImage[] $images
     * @param object[]    $persisted entités passées à persist()
     */
    private function createService(string $instance, array $helps = [], array $faqs = [], array $images = [], array &$persisted = []): HelpTransferService
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $helpRepository = $this->createStub(HelpRepository::class);
        $helpRepository->method('findBy')->willReturn($helps);
        $helpRepository->method('findOneBy')->willReturnCallback(
            fn (array $criteria) => $this->find($helps, static fn (Help $h) => $h->getRouteSlug() === $criteria['routeSlug'])
        );

        $faqRepository = $this->createStub(FaqRepository::class);
        $faqRepository->method('findBy')->willReturn($faqs);
        $faqRepository->method('findOneBy')->willReturnCallback(
            fn (array $criteria) => $this->find($faqs, static fn (Faq $f) => $f->getQuestion() === $criteria['question'])
        );

        $imageRepository = $this->createStub(HelpImageRepository::class);
        $imageRepository->method('findBy')->willReturn($images);
        $imageRepository->method('findOneBy')->willReturnCallback(
            fn (array $criteria) => $this->find($images, static fn (HelpImage $i) => $i->getFichier() === $criteria['fichier'])
        );

        $routes = new RouteCollection();
        $routes->add('app_help_index', new Route('/administration/help/'));
        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($routes);

        $secureUpload = new SecureUploadService($this->filesystem, [
            'help_images' => [
                'target_dir' => $this->workDir . '/' . $instance,
                'allowed_extensions' => ['png', 'jpg', 'jpeg'],
                'allowed_mime_types' => ['image/png', 'image/jpeg'],
                'max_size' => 10485760,
            ],
        ]);

        return new HelpTransferService($em, $helpRepository, $faqRepository, $imageRepository, $secureUpload, $router);
    }

    private function buildArchive(?array $manifest, array $files): string
    {
        $path = $this->tempFile();
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::OVERWRITE);
        if ($manifest !== null) {
            $zip->addFromString('manifest.json', json_encode(
                ['format' => HelpTransferService::FORMAT, 'version' => HelpTransferService::VERSION] + $manifest,
                JSON_THROW_ON_ERROR
            ));
        }
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return $path;
    }

    private function tempFile(): string
    {
        return $this->tempFiles[] = (string)tempnam(sys_get_temp_dir(), 'help_test_');
    }

    private function find(array $items, callable $predicate): ?object
    {
        foreach ($items as $item) {
            if ($predicate($item)) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return ($required is true ? T : T|null)
     */
    private function firstOf(array $entities, string $class, bool $required = true): ?object
    {
        foreach ($entities as $entity) {
            if ($entity instanceof $class) {
                return $entity;
            }
        }

        if ($required) {
            self::fail('Aucune entité ' . $class . ' persistée.');
        }

        return null;
    }
}
