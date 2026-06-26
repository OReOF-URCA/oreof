<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file //wsl.localhost/Ubuntu/home/louca/oreof-stack/oreofv2/src/Twig/HelpExtension.php
 * @author louca
 * @project oreofv2
 * @lastUpdate 19/06/2026 9:00
 */


namespace App\Twig;

use App\Entity\Help;
use App\Entity\User;
use App\Service\HelpGrantService;
use Doctrine\ORM\EntityManagerInterface;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Table\TableExtension; // L'extension magique pour les tableaux
use League\CommonMark\MarkdownConverter;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class HelpExtension extends AbstractExtension
{
    private MarkdownConverter $converter;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security               $security,
        private readonly HelpGrantService       $helpGrantService,
    )
    {
        $config = [
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
        ];
        $environment = new Environment($config);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new TableExtension());

        $this->converter = new MarkdownConverter($environment);
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('get_page_help', $this->getPageHelp(...))];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('parse_embeds', $this->parseEmbeds(...), ['is_safe' => ['html']]),
            new TwigFilter('markdown_to_html', $this->markdownToHtml(...), ['is_safe' => ['html']]),
            new TwigFilter('help_markdown', $this->helpMarkdown(...), ['is_safe' => ['html']]),
        ];
    }

    public function helpMarkdown(string $content): string
    {
        $cleanContent = str_replace("\r\n", "\n", $content);

        return $this->converter->convert($cleanContent)->getContent();
    }

    public function getPageHelp(string $routeSlug = null): ?Help
    {
        if (!$routeSlug) {
            return null;
        }

        $help = $this->em->getRepository(Help::class)->findOneBy(['routeSlug' => $routeSlug, 'isActive' => true]);
        if (!$help) {
            return null;
        }

        $user = $this->security->getUser();
        if ($this->helpGrantService->isAllowed($help, $user instanceof User ? $user : null)) {
            return $help;
        }

        return null;
    }

    public function markdownToHtml(string $content): string
    {
        $cleanContent = str_replace("\r\n", "\n", $content);

        return $this->converter->convert($cleanContent)->getContent();
    }

    public function parseEmbeds(string $content): string
    {
        // syntaxe attendue dans le markdown: [embed](https://youtube.com/watch?v=xxx)
        // en html apres markdown: <a href="https://youtube.com/watch?v=xxx">embed</a>
        $pattern = '/<a href="([^"]+)">(embed|video)<\/a>/i';

        return preg_replace_callback($pattern, function ($matches) {
            $url = $matches[1];
            
            $url = str_replace(
                ['watch?v=', 'youtu.be/', 'vimeo.com/'],
                ['embed/', 'youtube.com/embed/', 'player.vimeo.com/video/'],
                $url
            );

            return sprintf(
                '<div class="ratio ratio-16x9 my-3 rounded overflow-hidden shadow-sm"><iframe src="%s" allowfullscreen></iframe></div>',
                $url
            );
        }, $content);
    }
}