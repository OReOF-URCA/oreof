<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/TypeDiplome/Synchronisation/But.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 24/05/2023 08:57
 */

namespace App\TypeDiplome\Synchronisation;

use App\Entity\ButApprentissageCritique;
use App\Entity\ButCompetence;
use App\Entity\ButNiveau;
use App\Entity\CampagneCollecte;
use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;
use App\Entity\FicheMatiereMutualisable;
use App\Entity\Formation;
use App\Entity\Langue;
use App\Entity\Mccc;
use App\Entity\NatureUeEc;
use App\Entity\Parcours;
use App\Entity\Semestre;
use App\Entity\SemestreParcours;
use App\Entity\TypeEc;
use App\Entity\TypeUe;
use App\Entity\Ue;
use App\Enums\ModaliteEnseignementEnum;
use App\Enums\RegimeInscriptionEnum;
use App\Repository\ElementConstitutifRepository;
use App\Repository\LangueRepository;
use App\Repository\NatureUeEcRepository;
use App\Repository\TypeEcRepository;
use App\Repository\TypeUeRepository;
use App\Utils\Tools;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class But
{
    protected Formation $formation;
    protected bool $type3;
    protected array $listeParcours = [];
    protected array $structure = [];
    protected array $listeAcs = [];
    private ?TypeEc $typeEcRessource;
    private ?TypeEc $typeEcSae;
    private ?NatureUeEc $natureEc;
    private ?NatureUeEc $natureUe;
    private ?TypeUe $typeUe;
    private ?Langue $francais;
    private string $baseDir;
    private CampagneCollecte $campagneCollecte;

    public function __construct(
        LangueRepository                       $langueRepository,
        TypeEcRepository                       $typeEcRepository,
        TypeUeRepository                       $typeUeRepository,
        NatureUeEcRepository                   $natureUeEcRepository,
        protected EntityManagerInterface       $entityManager,
        protected HttpClientInterface          $client,
        protected ParameterBagInterface        $container,
        protected ElementConstitutifRepository $elementConstitutifRepository,
        private KernelInterface                $kernel,
    ) {
        $this->typeEcRessource = $typeEcRepository->findOneBy(['libelle' => 'Ressource']);
        $this->typeEcSae = $typeEcRepository->findOneBy(['libelle' => 'SAE']);
        $this->natureEc = $natureUeEcRepository->findOneBy(['libelle' => 'EC obligatoire']);
        $this->natureUe = $natureUeEcRepository->findOneBy(['libelle' => 'UE Obligatoire']);
        $this->typeUe = $typeUeRepository->findOneBy(['libelle' => 'Disciplinaire']);
        $this->francais = $langueRepository->findOneBy(['codeIso' => 'fr']);
        $this->baseDir = $this->kernel->getProjectDir() . '/public';
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface|JsonException
     */
    public function synchroniser(Formation $formation, CampagneCollecte $campagneCollecte): bool
    {
        $this->campagneCollecte = $campagneCollecte;
        $this->formation = $formation;
        $response = $this->client->request(
            'GET',
            $this->container->get('api_url') . '/departements/' . strtolower($formation->getSigle()),
            [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ]
            ]
        );

        $response2 = $this->client->request(
            'GET',
            $this->container->get('api_url') . '/specialite/' . strtolower($formation->getSigle()) . '/competences?page=1',
            [
                'headers' => [
                    'Accept' => 'application/ld+json',
                    'Content-Type' => 'application/ld+json'
                ]
            ]
        );

        if ($response->getStatusCode() === 200 && $response2->getStatusCode() === 200) {
            $this->clearRefCompetences();
            $this->clearParcours();
            $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $data2 = json_decode($response2->getContent(), true, 512, JSON_THROW_ON_ERROR);


            $this->initFormation($data);

            $this->ecritRefCompetences($data2['hydra:member']);
            $this->initParcours($data);
        }

        $page = 1;
        $response2 = $this->client->request(
            'GET',
            $this->container->get('api_url') . '/specialite/' . strtolower($formation->getSigle()) . '/ressources?page=' . $page,
            [
                'headers' => [
                    'Accept' => 'application/ld+json',
                    'Content-Type' => 'application/ld+json'
                ]
            ]
        );
        $data2 = json_decode($response2->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->addRessources($data2['hydra:member']);

        $response2 = $this->client->request(
            'GET',
            $this->container->get('api_url') . '/specialite/' . strtolower($formation->getSigle()) . '/saes?page=' . $page,
            [
                'headers' => [
                    'Accept' => 'application/ld+json',
                    'Content-Type' => 'application/ld+json'
                ]
            ]
        );
        $data2 = json_decode($response2->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->addSaes($data2['hydra:member']);
        $this->entityManager->flush();

        return true;
    }

    private function ecritRefCompetences(mixed $data): void
    {
        foreach ($data as $competence) {
            $this->ecritRefCompetence($competence);
        }
    }

    private function ecritRefCompetence(mixed $competence): void
    {
        $comp = new ButCompetence();
        $comp->setFormation($this->formation);
        $comp->setNomCourt($competence['nom_court']);
        $comp->setLibelle($competence['libelle']);
        $comp->setNumero($competence['numero']);

        $tSituations = [];
        foreach ($competence['apcSituationProfessionnelles'] as $situationProfessionnelle) {
            $tSituations[] = $situationProfessionnelle['libelle'];
        }
        $comp->setSituations($tSituations);

        $tComposantes = [];
        foreach ($competence['apcComposanteEssentielles'] as $composanteEssentielle) {
            $tComposantes[] = $composanteEssentielle['libelle'];
        }
        $comp->setComposantes($tComposantes);

        foreach ($competence['apcNiveaux'] as $niveau) {
            $niv = new ButNiveau();
            $niv->setLibelle($niveau['libelle']);
            $niv->setOrdre($niveau['ordre']);
            $niv->setAnnee($niveau['annee']['libelle']);
            $niv->setCompetence($comp);
            $comp->addButNiveau($niv);
            $this->entityManager->persist($niv);
            foreach ($niveau['apcApprentissageCritiques'] as $acs) {
                $ac = new ButApprentissageCritique();
                $ac->setLibelle($acs['libelle']);
                $ac->setCode($acs['code']);
                $ac->setNiveau($niv);
                $niv->addButApprentissageCritique($ac);
                $this->entityManager->persist($ac);
                $this->listeAcs[$acs['code']] = $ac;
            }
        }

        $this->entityManager->persist($comp);
        $this->entityManager->flush();
    }

    private function clearRefCompetences(): void
    {
        foreach ($this->formation->getButCompetences() as $competence) {
            foreach ($competence->getButNiveaux() as $niveau) {
                foreach ($niveau->getButApprentissageCritiques() as $ac) {
                    $this->entityManager->remove($ac);
                }
                $this->entityManager->remove($niveau);
            }
            $this->entityManager->remove($competence);
        }
        $this->entityManager->flush();
    }

    public function clearParcours(): void
    {
        $sem = [];
        $semP = [];
        $semFiches = [];
        foreach ($this->formation->getParcours() as $parcours) {
            foreach ($parcours->getSemestreParcours() as $semestreParcour) {
                $sem[] = $semestreParcour->getSemestre();
                $semP[] = $semestreParcour;
            }

            foreach ($parcours->getFicheMatieres() as $fmp) {
                foreach ($fmp->getFicheMatiereParcours() as $fp) {
                    $this->entityManager->remove($fp);
                }

                $this->entityManager->remove($fmp);
            }

            foreach ($parcours->getFicheMatiereParcours() as $fmp) {
                $this->entityManager->remove($fmp);
            }

            foreach ($parcours->getSemestreMutualisables() as $semestreMutualisable) {
                $this->entityManager->remove($semestreMutualisable);
            }

            foreach ($parcours->getUeMutualisables() as $ueMutualisable) {
                $this->entityManager->remove($ueMutualisable);
            }

            $this->entityManager->remove($parcours);
        }

        foreach ($sem as $semestre) {
            foreach ($semestre->getUes() as $ue) {
                foreach ($ue->getElementConstitutifs() as $uc) {
                    foreach ($uc->getFicheMatiere()->getFicheMatiereParcours() as $fmm) {
                        $this->entityManager->remove($fmm);
                    }
                    $semFiches[] = $uc->getFicheMatiere();
                    $this->entityManager->remove($uc);
                }
                $this->entityManager->remove($ue);
            }
            $this->entityManager->remove($semestre);
        }

        foreach ($semP as $semestreParcour) {
            $this->entityManager->remove($semestreParcour);
        }

        foreach ($semFiches as $semFiche) {
            $this->entityManager->remove($semFiche);
        }

        $this->entityManager->flush();
    }

    private function initParcours(mixed $data): void
    {
        $tronc = [];
        $addOne = true;

        if (!$this->type3) {
            for ($i = 1; $i <= 2; $i++) {
                $semestre = new Semestre();
                $semestre->setOrdre($i);
                $semestre->setTroncCommun(true);
                $this->entityManager->persist($semestre);
                $tronc[$i] = $semestre;
            }
        }

        foreach ($data['apcParcours'] as $parcours) {
            if ($parcours['dispense'] === true) {
                $this->structure[$parcours['id']] = [];
                $listeSemestre = [];
                $parc = new Parcours($this->formation);
                $parc->setLibelle($parcours['libelle']);
                $parc->setSigle($parcours['code']);
                $parc->setContenuFormation($parcours['textePresentation']);
                $state = $parc->getEtatSteps();
                $state['3'] = true;
                $parc->setEtatSteps($state);
                $this->entityManager->persist($parc);
                $this->listeParcours[$parcours['id']] = $parc;

                //si type 3 idem, si type 1 ou 2 tronc commun
                if ($this->type3) {
                    $debut = 1;
                } else {
                    $debut = 3;
                    for ($i = 1; $i <= 2; $i++) {
                        $semParc = new SemestreParcours($tronc[$i], $parc);
                        $semParc->setOrdre($i);
                        $semParc->setPorteur(true);
                        $this->entityManager->persist($semParc);
                        $tronc[$i]->addSemestreParcour($semParc);

                        $listeSemestre[$i] = $tronc[$i];
                        $this->structure[$parcours['id']][$i]['semestre'] = $tronc[$i];
                        $this->structure[$parcours['id']][$i]['ues'] = [];
                    }
                }

                //création des semestres
                for ($i = $debut; $i <= 6; $i++) {
                    $this->structure[$parcours['id']][$i] = [];
                    $semestre = new Semestre();
                    $semestre->setOrdre($i);
                    $semestre->setTroncCommun(false);
                    $this->entityManager->persist($semestre);

                    $semParc = new SemestreParcours($semestre, $parc);
                    $semParc->setOrdre($i);
                    $semParc->setPorteur(true);
                    $this->entityManager->persist($semParc);
                    $semestre->addSemestreParcour($semParc);
                    $this->structure[$parcours['id']][$i]['semestre'] = $semestre;
                    $this->structure[$parcours['id']][$i]['ues'] = [];
                    $listeSemestre[$i] = $semestre;
                }

                //synchron Parcours / Compétences / UE
                foreach ($parcours['apcParcoursNiveaux'] as $apcParcoursNiveau) {
                    $annee = $apcParcoursNiveau['niveau']['annee']['libelle'];
                    //todo: ne faire qu'une seule fois BUT1 si pas type 3
                    $offset = 0;
                    if ($annee === 'BUT2') {
                        $offset = 2;
                    } elseif ($annee === 'BUT3') {
                        $offset = 4;
                    }

                    if ((!$this->type3 && $addOne === true && $annee === 'BUT1') || $annee !== 'BUT1' || $this->type3) {
                        for ($j = 1; $j <= 2; $j++) {
                            $ue = new Ue();
                            $ue->setTypeUe($this->typeUe);
                            $ue->setNatureUeEc($this->natureUe);
                            $ue->setOrdre($apcParcoursNiveau['niveau']['competence']['id']);//numéro de la compétence = ordre
                            $ue->setLibelle($apcParcoursNiveau['niveau']['competence']['nom_court']); //nom court de la compétence
                            $ue->setSemestre($listeSemestre[$offset + $j]);
                            $this->structure[$parcours['id']][$offset + $j]['ues'][$apcParcoursNiveau['niveau']['competence']['id']] = $ue;
                            $this->entityManager->persist($ue);
                        }
                    }
                }
                $addOne = false;
            }
        }
    }

    private function initFormation(mixed $data): void
    {
        $this->formation->setHasParcours(true);
        $sem = [];
        if ($data['typeStructure'] === 'type_3') {
            $this->type3 = true;
            $sem[1] = 'parcours';
            $sem[2] = 'parcours';
        } else {
            $this->type3 = false;
            $sem[1] = 'tronc_commun';
            $sem[2] = 'tronc_commun';
        }

        for ($i = 3; $i <= 6; $i++) {
            $sem[$i] = 'parcours';
        }

        $this->formation->setStructureSemestres($sem);
    }

    private function addRessources(array $data): void
    {
        foreach ($data as $ressource) {
            $this->addRessource($ressource);
        }
    }

    private function addSaes(array $data): void
    {
        foreach ($data as $sae) {
            $this->addSae($sae);
        }
    }

    private function addRessource(array $ressource): void
    {
        $fm = new FicheMatiere();
        $fm->setCampagneCollecte($this->campagneCollecte);

        $fm->setTypeMatiere(FicheMatiere::TYPE_MATIERE_RESSOURCE);
        $fm->setLibelle($ressource['libelle']);
        $fm->setSigle($ressource['codeMatiere']);
        $fm->setDescription($ressource['description']);
        $fm->addLangueDispense($this->francais);
        $fm->addLangueSupport($this->francais);
        $this->entityManager->persist($fm);

        if (!array_key_exists('apcRessourceParcours', $ressource) || count($ressource['apcRessourceParcours']) === 0) {
            //si tronc commun
            if ($this->formation->getStructureSemestres()[$ressource['semestre']['ordreLmd']] === 'tronc_commun') {
                $fm->setEnseignementMutualise(false);
                $fm->setParcours($this->listeParcours[array_key_first($this->listeParcours)]);
                $this->genereEcbyUE($ressource, $fm, array_key_first($this->listeParcours));
            } else {
                $first = true;
                //tous les parcours et mutualisé
                foreach ($this->listeParcours as $key => $parcours) {
                    $this->genereEcbyUE($ressource, $fm, $key);

                    if ($first === false) {
                        $fm->setEnseignementMutualise(true);
                        $mutualise = new FicheMatiereMutualisable();
                        $mutualise->setFicheMatiere($fm);
                        $mutualise->setParcours($parcours);
                        $this->entityManager->persist($mutualise);
                    } else {
                        $fm->setParcours($parcours);
                    }
                    $first = false;
                }
            }
        } else {
            $first = true;
            foreach ($ressource['apcRessourceParcours'] as $parc) {
                if (array_key_exists($parc['parcours']['id'], $this->listeParcours)) {
                    $this->genereEcbyUE($ressource, $fm, $parc['parcours']['id']);
                    if ($first === false) {
                        $fm->setEnseignementMutualise(true);
                        $mutualise = new FicheMatiereMutualisable();
                        $mutualise->setFicheMatiere($fm);
                        $mutualise->setParcours($this->listeParcours[$parc['parcours']['id']]);
                        $this->entityManager->persist($mutualise);
                    } else {
                        $fm->setParcours($this->listeParcours[$parc['parcours']['id']]);
                    }
                    $first = false;
                }
            }
        }
    }

    private function addSae(array $sae): void
    {
        $fm = new FicheMatiere();
        $fm->setCampagneCollecte($this->campagneCollecte);

        $fm->setTypeMatiere(FicheMatiere::TYPE_MATIERE_SAE);
        $fm->setLibelle($sae['libelle']);
        $fm->setObjectifs($sae['objectifs']);
        $fm->setSigle($sae['codeMatiere']);
        $fm->setDescription($sae['description']);
        $this->entityManager->persist($fm);

        if (!array_key_exists('apcSaeParcours', $sae) || count($sae['apcSaeParcours']) === 0) {
            //si tronc commun
            if ($this->formation->getStructureSemestres()[$sae['semestre']['ordreLmd']] === 'tronc_commun') {
                $fm->setEnseignementMutualise(false);
                $fm->setParcours($this->listeParcours[array_key_first($this->listeParcours)]);
                $this->genereEcSaebyUE($sae, $fm, array_key_first($this->listeParcours));
            } else {
                $first = true;
                //tous les parcours et mutualisé
                foreach ($this->listeParcours as $key => $parcours) {
                    $this->genereEcSaebyUE($sae, $fm, $key);
                    if ($first === false) {
                        $fm->setEnseignementMutualise(true);
                        $mutualise = new FicheMatiereMutualisable();
                        $mutualise->setFicheMatiere($fm);
                        $mutualise->setParcours($parcours);
                        $this->entityManager->persist($mutualise);
                    } else {
                        $fm->setParcours($parcours);
                    }
                    $first = false;
                }
            }
        } else {
            $first = true;
            foreach ($sae['apcSaeParcours'] as $parc) {
                if (array_key_exists($parc['parcours']['id'], $this->listeParcours)) {
                    $this->genereEcSaebyUE($sae, $fm, $parc['parcours']['id']);
                    if ($first === false) {
                        $fm->setEnseignementMutualise(true);
                        $mutualise = new FicheMatiereMutualisable();
                        $mutualise->setFicheMatiere($fm);
                        $mutualise->setParcours($this->listeParcours[$parc['parcours']['id']]);
                        $this->entityManager->persist($mutualise);
                    } else {
                        $fm->setParcours($this->listeParcours[$parc['parcours']['id']]);
                    }
                    $first = false;
                }
            }
        }
    }

    private function genereEcbyUE(array $ressource, FicheMatiere $fm, int $keyParcours): void
    {
        foreach ($ressource['apcRessourceCompetences'] as $competence) {
            if (array_key_exists($keyParcours, $this->structure) && array_key_exists($competence['competence']['id'], $this->structure[$keyParcours][$ressource['semestre']['ordreLmd']]['ues'])) {
                $ec = new ElementConstitutif();
                $ec->setModaliteEnseignement(ModaliteEnseignementEnum::PRESENTIELLE);
                $ec->setOrdre($ressource['ordre']);
                $ec->setNatureUeEc($this->natureEc);
                $ec->setParcours($this->listeParcours[$keyParcours]);
                $ec->setTypeEc($this->typeEcRessource);
                $ec->setEcts($competence['coefficient']);
                $ec->setLibelle($ressource['libelle']);
                $ec->setUe($this->structure[$keyParcours][$ressource['semestre']['ordreLmd']]['ues'][$competence['competence']['id']]);
                $ec->setFicheMatiere($fm);
                $ec->genereCode();
                $this->entityManager->persist($ec);

                //ajout des apprentissages critiques en fonction de l'UE
                foreach ($ressource['apcRessourceApprentissageCritiques'] as $apprentissageCritique) {
                    $codeAc = $apprentissageCritique['apprentissageCritique']['code'];
                    if ((int)substr($codeAc, 3, 1) === $competence['competence']['id']) {
                        $ac = $this->listeAcs[$codeAc];
                        $fm->addApprentissagesCritique($ac);
                        //todo: récupe des Coeffs ???
                    }
                }
            }
        }
    }

    private function genereEcSaebyUE(array $sae, FicheMatiere $fm, int $keyParcours): void
    {
        foreach ($sae['apcSaeCompetences'] as $competence) {
            if (array_key_exists($keyParcours, $this->structure) && array_key_exists($competence['competence']['id'], $this->structure[$keyParcours][$sae['semestre']['ordreLmd']]['ues'])) {
                $ec = new ElementConstitutif();
                $ec->setModaliteEnseignement(ModaliteEnseignementEnum::PRESENTIELLE);
                if ((int)$sae['ordre'] === 99) {
                    $ec->setOrdre(60);
                } else {
                    $ec->setOrdre($sae['ordre'] + 50);
                }
                $ec->setNatureUeEc($this->natureEc);
                $ec->setParcours($this->listeParcours[$keyParcours]);
                $ec->setTypeEc($this->typeEcSae);
                $ec->setLibelle($sae['libelle']);
                $ec->setEcts($competence['coefficient']);
                $ec->setUe($this->structure[$keyParcours][$sae['semestre']['ordreLmd']]['ues'][$competence['competence']['id']]);
                $ec->setFicheMatiere($fm);
                $ec->genereCode();
                $this->entityManager->persist($ec);

                //ajout des apprentissages critiques en fonction de l'UE
                foreach ($sae['apcSaeApprentissageCritiques'] as $apprentissageCritique) {
                    $codeAc = $apprentissageCritique['apprentissageCritique']['code'];
                    if ((int)substr($codeAc, 3, 1) === $competence['competence']['id']) {
                        $ac = $this->listeAcs[$codeAc];
                        $fm->addApprentissagesCritique($ac);
                    }
                }
            }
        }
    }

    public function synchroniserMccc(Formation $formation): void
    {
        $fifc = 'fi';
        foreach ($formation->getRegimeInscription() as $regime) {
            if ($regime === RegimeInscriptionEnum::FI || $regime === RegimeInscriptionEnum::FC) {
                $fifc = 'fi';
            } else {
                $fifc = 'alt';
            }
        }
        $sigle = strtoupper($formation->getSigle());
        //ouvrir le fichier excel qui se trouve dans public/but
        $spreadsheet = IOFactory::load($this->baseDir . '/but/MCCC_' . $fifc . '_' . $sigle . ' 2023-2024.xlsx');

        $ligneDebut = 22;

        $ecs = $this->elementConstitutifRepository->findByFormation($formation);
        $tabEcs = [];
        foreach ($ecs as $ec) {
            $fiche = $ec->getFicheMatiere();
            $tabEcs[$fiche->getSigle()] = $fiche;
            foreach ($ec->getMcccs() as $mccc) {
                $this->entityManager->remove($mccc);
            }
        }

        for ($i = 1; $i <= 6; $i++) {
            //tester sur l'onglet $i exsite dans le fichier excel et le selectionner
            if ($spreadsheet->sheetNameExists('Semestre ' . $i)) {
                $sheet = $spreadsheet->getSheetByName('Semestre ' . $i);
                if ($sheet !== null) {
                    $ligne = $ligneDebut;
                    while ($sheet->getCell('H' . $ligne)->getValue() !== 'Date du vote de la CFVU :' && $ligne < 100) {
                        $codeEc = $sheet->getCell('B' . $ligne)->getValue();
                        if (array_key_exists($codeEc, $tabEcs)) {
                            //heures
                            // G => CM
                            // H => TD
                            // I => TP
                            // J => PRJ
                            $ec = $tabEcs[$codeEc];
                            $ec->setVolumeCmPresentiel(Tools::convertToFloat($sheet->getCell('G' . $ligne)->getValue()));
                            $ec->setVolumeTdPresentiel(Tools::convertToFloat($sheet->getCell('H' . $ligne)->getValue()));
                            $ec->setVolumeTpPresentiel(Tools::convertToFloat($sheet->getCell('I' . $ligne)->getValue()));
                            $ec->setVolumeTe(Tools::convertToFloat($sheet->getCell('J' . $ligne)->getValue()));

                            $tabMcccs = [
                                'N' => ['td_tp_oral', 'O'],
                                'P' => ['td_tp_ecrit', 'Q'],
                                'R' => ['td_tp_rapport', 'S'],
                                'T' => ['td_tp_autre', 'U'],
                                'V' => ['cm_ecrit', 'W'],
                                'X' => ['cm_rapport', 'Y'],
                                'Z' => ['iut_portfolio', 'AA'],
                                'AB' => ['iut_livrable', 'AC'],
                                'AD' => ['iut_rapport', 'AE'],
                                'AF' => ['iut_soutenance', 'AG'],
                                'AH' => ['hors_iut_entreprise', 'AI'],
                                'AJ' => ['hors_iut_rapport', 'AK'],
                                'AL' => ['hors_iut_soutenance', 'AM'],
                            ];

                            foreach ($ec->getMcccs() as $mccc) {
                                $this->entityManager->remove($mccc);
                            }
                            $totalPourcentage = 0;
                            foreach ($tabMcccs as $key => $value) {
                                // MCCC
                                if ($sheet->getCell($key . $ligne)->getValue() !== '') {
                                    $mccc = new Mccc();
                                    $mccc->setTypeEpreuve([$value[0]]);
                                    $mccc->setPourcentage(Tools::convertToFloat($sheet->getCell($key . $ligne)->getValue()) * 100);
                                    $mccc->setNbEpreuves((int)$sheet->getCell($value[1] . $ligne)->getValue());
                                    $mccc->setLibelle($value[0]);
                                    $mccc->setControleContinu(true);
                                    $mccc->setNumeroSession(1);
                                    $mccc->setExamenTerminal(false);
                                    $totalPourcentage += $mccc->getPourcentage();
                                    $this->entityManager->persist($mccc);
                                    $ec->addMccc($mccc);
                                }
                            }

                            if ($totalPourcentage === 100.0) {
                                $ec->setEtatMccc('Complet');
                            } else {
                                $ec->setEtatMccc(null);
                            }
                        }
                        $ligne++;
                    }
                    $this->entityManager->flush();
                }
            }
        }
    }
}
