<?php

declare(strict_types=1);

namespace App\Twig\Components\UI\Breadcrumb;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('Breadcrumb:Item', template: 'components/Breadcrumb/Item.html.twig')]
final class BreadcrumbItem
{
}
