<?php

namespace App\Tests\Unit\Classes;

use App\Classes\GetFormations;
use App\Entity\Composante;
use App\Entity\Formation;
use App\Entity\Mention;
use App\Entity\TypeDiplome;
use App\Repository\FormationRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class GetFormationsTest extends TestCase
{
    private GetFormations $getFormations;

    protected function setUp(): void
    {
        $formationRepository = $this->createStub(FormationRepository::class);
        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);

        $this->getFormations = new GetFormations(
            $formationRepository,
            $authorizationChecker,
        );
    }

    public function testSortFormationsByMentionAlphabetical(): void
    {
        $mBio = new Mention();
        $mBio->setLibelle('Biologie');

        $mChim = new Mention();
        $mChim->setLibelle('Chimie');

        $mInfo = new Mention();
        $mInfo->setLibelle('Informatique');

        $f1 = new Formation();
        $f1->setMention($mInfo);
        $this->setId($f1, 1);

        $f2 = new Formation();
        $f2->setMention($mBio);
        $this->setId($f2, 2);

        $f3 = new Formation();
        $f3->setMention($mChim);
        $this->setId($f3, 3);

        $input = [1 => $f1, 2 => $f2, 3 => $f3];

        // Par défaut (mention asc)
        $sorted = $this->getFormations->sortFormations($input, 'mention', 'asc');
        $keys = array_keys($sorted);
        $this->assertSame([2, 3, 1], $keys); // Bio (2), Chimie (3), Info (1)

        // Descendant
        $sortedDesc = $this->getFormations->sortFormations($input, 'mention', 'desc');
        $keysDesc = array_keys($sortedDesc);
        $this->assertSame([1, 3, 2], $keysDesc);

        // Sort = 'default'
        $sortedDefault = $this->getFormations->sortFormations($input, 'default', 'asc');
        $keysDefault = array_keys($sortedDefault);
        $this->assertSame([2, 3, 1], $keysDefault);
    }

    public function testSortFormationsSecondarySortByMention(): void
    {
        $tdLicence = new TypeDiplome();
        $tdLicence->setLibelle('Licence');

        $mBio = new Mention();
        $mBio->setLibelle('Biologie');

        $mInfo = new Mention();
        $mInfo->setLibelle('Informatique');

        $f1 = new Formation();
        $f1->setTypeDiplome($tdLicence);
        $f1->setMention($mInfo);
        $this->setId($f1, 1);

        $f2 = new Formation();
        $f2->setTypeDiplome($tdLicence);
        $f2->setMention($mBio);
        $this->setId($f2, 2);

        // Même typeDiplome, le tri secondaire doit trier par mention alphabétique
        $sorted = $this->getFormations->sortFormations([1 => $f1, 2 => $f2], 'typeDiplome', 'asc');
        $this->assertSame([2, 1], array_keys($sorted));
    }

    private function setId(Formation $formation, int $id): void
    {
        $reflection = new \ReflectionProperty(Formation::class, 'id');
        $reflection->setValue($formation, $id);
    }
}

