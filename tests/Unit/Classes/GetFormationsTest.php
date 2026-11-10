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

        // Par mention asc
        $sorted = $this->getFormations->sortFormations($input, 'mention', 'asc');
        $keys = array_keys($sorted);
        $this->assertSame([2, 3, 1], $keys); // Bio (2), Chimie (3), Info (1)

        // Descendant
        $sortedDesc = $this->getFormations->sortFormations($input, 'mention', 'desc');
        $keysDesc = array_keys($sortedDesc);
        $this->assertSame([1, 3, 2], $keysDesc);
    }

    public function testSortFormationsByDefaultIsComposanteThenTypeDiplomeThenMention(): void
    {
        $compDroit = new Composante();
        $compDroit->setLibelle('Droit');
        $this->setEntityId($compDroit, 99); // ID élevé pour prouver qu'on trie sur le texte et pas sur l'ID

        $compLettres = new Composante();
        $compLettres->setLibelle('Lettres');
        $this->setEntityId($compLettres, 1);

        $tdBut = new TypeDiplome();
        $tdBut->setLibelle('BUT');
        $this->setEntityId($tdBut, 50);

        $tdLicence = new TypeDiplome();
        $tdLicence->setLibelle('Licence');
        $this->setEntityId($tdLicence, 2);

        $mBio = new Mention();
        $mBio->setLibelle('Biologie');

        $mChim = new Mention();
        $mChim->setLibelle('Chimie');

        // f1: Lettres, BUT, Biologie
        $f1 = new Formation();
        $f1->setComposantePorteuse($compLettres);
        $f1->setTypeDiplome($tdBut);
        $f1->setMention($mBio);
        $this->setEntityId($f1, 1);

        // f2: Droit, Licence, Biologie
        $f2 = new Formation();
        $f2->setComposantePorteuse($compDroit);
        $f2->setTypeDiplome($tdLicence);
        $f2->setMention($mBio);
        $this->setEntityId($f2, 2);

        // f3: Droit, BUT, Chimie
        $f3 = new Formation();
        $f3->setComposantePorteuse($compDroit);
        $f3->setTypeDiplome($tdBut);
        $f3->setMention($mChim);
        $this->setEntityId($f3, 3);

        // f4: Droit, BUT, Biologie
        $f4 = new Formation();
        $f4->setComposantePorteuse($compDroit);
        $f4->setTypeDiplome($tdBut);
        $f4->setMention($mBio);
        $this->setEntityId($f4, 4);

        $input = [1 => $f1, 2 => $f2, 3 => $f3, 4 => $f4];

        // Default sort : composante (text), typeDiplome (text), mention (text)
        // 1er: Droit + BUT + Biologie (f4)
        // 2e:  Droit + BUT + Chimie (f3)
        // 3e:  Droit + Licence + Biologie (f2)
        // 4e:  Lettres + BUT + Biologie (f1)
        $sortedDefault = $this->getFormations->sortFormations($input, 'default', 'asc');
        $this->assertSame([4, 3, 2, 1], array_keys($sortedDefault));
    }

    public function testSortFormationsHandlesFrenchAccentsBeforeZ(): void
    {
        $mEco = new Mention();
        $mEco->setLibelle('Économie');

        $mZoo = new Mention();
        $mZoo->setLibelle('Zoologie');

        $mBio = new Mention();
        $mBio->setLibelle('Biologie');

        $f1 = new Formation();
        $f1->setMention($mZoo);
        $this->setId($f1, 1);

        $f2 = new Formation();
        $f2->setMention($mEco);
        $this->setId($f2, 2);

        $f3 = new Formation();
        $f3->setMention($mBio);
        $this->setId($f3, 3);

        $input = [1 => $f1, 2 => $f2, 3 => $f3];

        $sorted = $this->getFormations->sortFormations($input, 'mention', 'asc');
        // 'Économie' doit se trouver AVANT 'Zoologie' et APRÈS 'Biologie' (pas après 'Z')
        $this->assertSame([3, 2, 1], array_keys($sorted)); // Bio (3), Économie (2), Zoologie (1)
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
        $this->setEntityId($formation, $id);
    }

    private function setEntityId(object $entity, int $id): void
    {
        $reflection = new \ReflectionProperty($entity::class, 'id');
        $reflection->setValue($entity, $id);
    }
}

