<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\DTO\MentionDto;
use App\Entity\Mention;
use App\Entity\TypeDiplome;
use PHPUnit\Framework\TestCase;

class MentionTypeDiplomeTest extends TestCase
{
    public function testAddAndRemoveTypeDiplomeOnMention(): void
    {
        $mention = new Mention();
        $this->assertCount(0, $mention->getTypeDiplomes());

        $td1 = new TypeDiplome();
        $td1->setLibelle('Licence');

        $td2 = new TypeDiplome();
        $td2->setLibelle('Master');

        $mention->addTypeDiplome($td1);
        $this->assertCount(1, $mention->getTypeDiplomes());
        $this->assertTrue($mention->getTypeDiplomes()->contains($td1));

        $mention->addTypeDiplome($td2);
        $this->assertCount(2, $mention->getTypeDiplomes());

        $mention->removeTypeDiplome($td1);
        $this->assertCount(1, $mention->getTypeDiplomes());
        $this->assertFalse($mention->getTypeDiplomes()->contains($td1));
        $this->assertTrue($mention->getTypeDiplomes()->contains($td2));
    }

    public function testMentionDtoCreateFromEntitySyncsTypeDiplomes(): void
    {
        $mention = new Mention();
        $mention->setLibelle('Informatique');

        $td = new TypeDiplome();
        $td->setLibelle('BUT');
        $mention->addTypeDiplome($td);

        $dto = MentionDto::createFromEntity($mention);
        $this->assertCount(1, $dto->typeDiplomes);
        $this->assertSame($td, $dto->typeDiplomes->first());
    }
}

