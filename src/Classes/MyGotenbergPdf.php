<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Classes/MyDomPdf.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 28/08/2023 15:54
 */

namespace App\Classes;

use App\Entity\Parcours;
use App\Utils\Tools;
use Sensiolabs\GotenbergBundle\Builder\BuilderFileInterface;
use Sensiolabs\GotenbergBundle\Builder\Pdf\HtmlPdfBuilder;
use Sensiolabs\GotenbergBundle\Debug\Builder\TraceableBuilder;
use Sensiolabs\GotenbergBundle\Enumeration\EmulatedMediaType;
use Sensiolabs\GotenbergBundle\GotenbergPdfInterface;
use Sensiolabs\GotenbergBundle\Processor\FileProcessor;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MyGotenbergPdf
{
    protected array $options;
    private string $basePath;

    private array $paperSizes = [
        'A4' => [8.3, 11.7],
        'A3' => [11.7, 16.5],
        'A2' => [16.5, 23.4],
    ];

    public function __construct(
        private readonly GotenbergPdfInterface $gotenberg,
        KernelInterface           $kernel
    ) {
        $this->basePath = $kernel->getProjectDir() . '/public';
    }

    public function render(string $template, array $context = [], string $name = 'fichier', array $options = []): Response
    {
        return $this->buildBuilder($template, $context, $name, $options)
            ->fileName($this->valideName($name))
            ->generate()
            ->stream()
        ;

    }

    /**
     * @return HtmlPdfBuilder
     */
    private function buildBuilder(string $template, array $context, string $name, array $options): object
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        $this->options = $resolver->resolve($options);

        $title = $this->resolveTitle($context);

        if ($this->options['withTemplate'] === true) {
            if (array_key_exists('composante', $context) && $context['composante'] !== null) {
                $this->options['composante'] = $context['composante'];
            }
        }

        [$width, $height] = $this->paperSizes[$this->options['paperSize']];

        $builder = $this->gotenberg->html()
            ->content($template, array_merge($context, $this->options))
            ->header('pdf/header.html.twig', $this->getHeader($title, $context))
            ->footer('pdf/footer.html.twig', $this->getFooter($context))
            ->emulatedMediaType(EmulatedMediaType::Print)
            ->printBackground()
            ->paperSize($width, $height)
            ->margins(1.3, 1.3, 0.8, 0.8)
        ;

        if ($this->options['landscape']) {
            $builder->landscape();
        }

        return $builder;
    }

    public function renderAndSave(string $template, string $dir, array $context = [], string $name = 'fichier', array $options = []): string
    {
        $validName = $this->valideName($name);

        $this->buildBuilder($template, $context, $name, $options)
            ->generate()
            ->processor(new FileProcessor(new Filesystem(), $dir))
            ->process();

        return $validName;
    }

//    private function generateHtml(string $template, array $context = []): string
//    {
//        $context = array_merge($context, $this->options);
//        return $this->twig->render($template, $context);
//    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'isRemoteEnabled' => true,
            'attachment' => true,
            'baseUrl' => $this->basePath,
            'withTemplate' => false,
            'landscape' => false,
            'paperSize' => 'A4',
            'composante' => null,
        ]);
    }

    private function valideName(string $name): string
    {
        if (!str_contains($name, '.pdf')) {
            $name .= '.pdf';
        }

        return Tools::FileName($name);
    }

    private function resolveTitle(array $context): string
    {
        if (array_key_exists('parcours', $context) && $context['parcours'] instanceof Parcours) {
            $parcours  = $context['parcours'];
            $formation = $parcours->getFormation();

            if (array_key_exists('titre', $context)) {
                return ($formation ? $formation->getDisplayLong() . ' &bull; ' : '')
                    . 'Parcours : ' . $parcours->getDisplay()
                    . ' &bull; ' . $context['titre'];
            }

            return ($formation ? $formation->getDisplayLong() . ' &bull; ' : '')
                . 'Parcours : ' . $parcours->getDisplay();
        }

        if (array_key_exists('titre', $context)) {
            return (string) $context['titre'];
        }

        return '';
    }

    private function getHeader(string $titre = '', array $context = []): array
    {
        if ($this->options['withTemplate']) {
            if (array_key_exists('composante', $context)
                && $context['composante'] !== null
                && $context['composante']->getHeaderPlaquette() !== null
            ) {
                $imagePath = $this->basePath . '/images/' . $context['composante']->getHeaderPlaquette();
            } else {
                $imagePath = $this->basePath . '/images/header.jpg';
            }
        } else {
            $imagePath = $this->basePath . '/images/logo_urca.png';
        }

        $imageContent = (is_file($imagePath) && is_readable($imagePath))
            ? base64_encode(file_get_contents($imagePath))
            : '';

        return [
            'baseUrl'      => $this->basePath,
            'titre'        => $titre,
            'image'        => $imageContent,
            'withTemplate' => $this->options['withTemplate'],
        ];
    }

    private function getFooter(array $context = []): array
    {
        $imageContent = '';

        if ($this->options['withTemplate']) {
            if (array_key_exists('composante', $context)
                && $context['composante'] !== null
                && $context['composante']->getFooterPlaquette() !== null
            ) {
                $imagePath = $this->basePath . '/images/' . $context['composante']->getFooterPlaquette();
            } else {
                $imagePath = $this->basePath . '/images/vague_urca.jpg';
            }

            if (is_file($imagePath) && is_readable($imagePath)) {
                $imageContent = base64_encode(file_get_contents($imagePath));
            }
        }

        return array_merge($this->options, [
            'image' => $imageContent,
            'final' => $context['final'] ?? false,
        ]);
    }
}
