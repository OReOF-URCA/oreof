<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/TypeDiplome/Licence/Dto/Mccc.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 19/10/2025 16:24
 */

namespace App\TypeDiplome\Diplomes\Licence\Dto;

use DateTimeInterface;

class Mccc
{

    public string $COL_MCCC_CC = '';
    public string $COL_MCCC_CCI = '';
    public string $COL_MCCC_CT = '';
    public string $COL_MCCC_SECONDE_CHANCE_CC_AVEC_TP = '';
    public string $COL_MCCC_SECONDE_CHANCE_CC_SANS_TP = '';
    public string $COL_MCCC_SECONDE_CHANCE_CC_SUP_10 = '';
    public string $COL_MCCC_SECONDE_CHANCE_CT = '';

    public function __construct(
        public array  $mcccs,
        public string $typeMccc,
        public array  $typeEpreuves,
        public bool   $isQuitus = false
    )
    {
    }

    public function calculDisplayMccc(): void
    {
        switch ($this->typeMccc) {
            case 'cc':
                $texte = '';
                $texteAvecTp = '';
                $hasTp = false;
                $pourcentageTp = 0;
                if (array_key_exists(1, $this->mcccs) && array_key_exists('cc', $this->mcccs[1])) {
                    $nb = 1;
                    $nb2 = 1;
                    /** @var \App\Entity\Mccc $mccc */
                    foreach ($this->mcccs[1]['cc'] as $mccc) {
                        for ($i = 1; $i <= $mccc->getNbEpreuves(); $i++) {
                            $texte .= 'CC' . $nb . ' (' . $mccc->getPourcentage() . '%); ';
                            $nb++;
                        }

                        if ($mccc->hasTp()) {
                            $hasTp = true;
                            if ($mccc->getNbEpreuves() === 1) {
                                //si une seule épreuve de CC, pas de prise en compte du %de TP en seconde session
                                //todo: interdire la saisie d'un pourcentage de TP si une seule épreuve de CC
                                $pourcentageTp += $mccc->getPourcentage();
                            } else {
                                $pourcentageTp += $mccc->pourcentageTp();
                            }
                            $texteAvecTp .= 'TPr' . $nb2 . ' (' . $mccc->getPourcentage() . '%); ';

                            $nb2++;
                        }
                    }

                    $texte = substr($texte, 0, -2);
                    $this->COL_MCCC_CC = $this->addQuitus($texte, $this->isQuitus);
                }

                if (array_key_exists(2, $this->mcccs) && array_key_exists('et', $this->mcccs[2]) && is_array($this->mcccs[2]['et'])) {
                    $texte = '';
                    $pourcentageTpEt = $pourcentageTp / count($this->mcccs[2]['et']);
                    foreach ($this->mcccs[2]['et'] as $mccc) {
                        $texte .= $this->displayTypeEpreuveWithDureePourcentage($mccc);
                        $texteAvecTp .= $this->displayTypeEpreuveWithDureePourcentageTp($mccc, $pourcentageTpEt);
                    }

                    $texte = substr($texte, 0, -2);
                }

                if ($hasTp) {
                    $texteAvecTp = substr($texteAvecTp, 0, -2);
                    $this->COL_MCCC_SECONDE_CHANCE_CC_AVEC_TP = $this->addQuitus(str_replace(';', '+', $texteAvecTp), $this->isQuitus);
                } else {
                    $this->COL_MCCC_SECONDE_CHANCE_CC_SANS_TP = $this->addQuitus($texte, $this->isQuitus);
                }

                break;
            case 'cci':
                $texte = '';
                /** @var \App\Entity\Mccc $mccc */
                foreach ($this->mcccs as $mccc) {
                    $texte .= 'CC' . $mccc->getNumeroSession() . ' (' . $mccc->getPourcentage() . '%); ';
                }
                $texte = substr($texte, 0, -2);
                $this->COL_MCCC_CCI = $this->addQuitus($texte, $this->isQuitus);
                break;
            case 'cc_ct':
                if (array_key_exists(1, $this->mcccs) && array_key_exists('cc', $this->mcccs[1]) && $this->mcccs[1]['cc'] !== null) {
                    $texte = '';
                    /** @var \App\Entity\Mccc $mccc */
                    foreach ($this->mcccs[1]['cc'] as $mccc) {
                        $texte .= 'CC ' . $mccc->getNbEpreuves() . ' épreuve(s) (' . $mccc->getPourcentage() . '%); ';
                    }
                    $texte = substr($texte, 0, -2);
                    $this->COL_MCCC_CC = $this->addQuitus($texte, $this->isQuitus);
                }

                $texteAvecTp = '';
                $texteCc = '';
                $pourcentageTp = 0;
                $pourcentageCc = 0;
                $hasTp = false;
                if (array_key_exists(1, $this->mcccs) && array_key_exists('cc', $this->mcccs[1])) {
                    foreach ($this->mcccs[1]['cc'] as $mccc) {
                        if ($mccc->hasTp()) {
                            $hasTp = true;
                            if ($mccc->getNbEpreuves() === 1) {
                                //si une seule épreuve de CC, pas de prise en compte du %de TP en seconde session
                                $pourcentageTp += $mccc->getPourcentage();
                            } else {
                                $pourcentageTp += $mccc->pourcentageTp();
                            }
                        }
                        $pourcentageCc += $mccc->getPourcentage();
                        $texteCc .= 'CC (' . $mccc->getPourcentage() . '%); ';
                    }

                    if ($hasTp) {
                        $texteAvecTp .= 'TPr (' . $pourcentageTp . '%); ';
                    }

                    if (array_key_exists('et', $this->mcccs[1]) && $this->mcccs[1]['et'] !== null) {
                        $texteEpreuve = '';
                        foreach ($this->mcccs[1]['et'] as $mccc) {
                            $texteEpreuve .= $this->displayTypeEpreuveWithDureePourcentage($mccc);
                        }

                        $texteEpreuve = substr($texteEpreuve, 0, -2);
                        $this->COL_MCCC_CT = $this->addQuitus($texteEpreuve, $this->isQuitus);
                    }
                }

                if (array_key_exists(2, $this->mcccs) && array_key_exists('et', $this->mcccs[2]) && $this->mcccs[2]['et'] !== null) {
                    $texteEpreuve = '';
                    $pourcentageTpEt = $pourcentageTp / count($this->mcccs[2]['et']);
                    if (count($this->mcccs[2]['et']) > 1) {
                        $facteur = (100 - $pourcentageCc) / 100;
                    } else {
                        $facteur = 1;
                    }
                    $pourcentageCcEt = $pourcentageCc / count($this->mcccs[2]['et']);

                    foreach ($this->mcccs[2]['et'] as $mccc) {
                        $texteEpreuve .= $this->displayTypeEpreuveWithDureePourcentage($mccc);
                        $texteAvecTp .= $this->displayTypeEpreuveWithDureePourcentageTp($mccc, $pourcentageTpEt);
                        $texteCc .= $this->displayTypeEpreuveWithDureePourcentageTp($mccc, $pourcentageCcEt, $facteur);
                    }

                    $texteEpreuve = substr($texteEpreuve, 0, -2);
                    $texteCc = substr($texteCc, 0, -2);
                    $texteCc = str_replace('CC', 'CCr', $texteCc);
                    $texteAvecTp = substr($texteAvecTp, 0, -2);

                    if ($hasTp) {
                        $this->COL_MCCC_SECONDE_CHANCE_CC_AVEC_TP = $this->addQuitus(str_replace(';', '+', $texteAvecTp), $this->isQuitus);
                    } else {
                        //si TP cette celulle est vide...
                        $this->COL_MCCC_SECONDE_CHANCE_CC_SANS_TP = $this->addQuitus($texteEpreuve, $this->isQuitus);
                    }
                    $this->COL_MCCC_SECONDE_CHANCE_CC_SUP_10 = $this->addQuitus(str_replace(';', '+', $texteCc), $this->isQuitus);
                }

                //on garde CC et on complète avec le reste de pourcentage de l'ET

                break;
            case 'ct':
                if (array_key_exists(1, $this->mcccs) && array_key_exists('et', $this->mcccs[1]) && $this->mcccs[1]['et'] !== null) {
                    $texteEpreuve = '';
                    foreach ($this->mcccs[1]['et'] as $mccc) {
                        $texteEpreuve .= $this->displayTypeEpreuveWithDureePourcentage($mccc);
                    }
                    $texteEpreuve = substr($texteEpreuve, 0, -2);
                    $this->COL_MCCC_CT = $this->addQuitus($texteEpreuve, $this->isQuitus);
                }

                if (array_key_exists(2, $this->mcccs) && array_key_exists('et', $this->mcccs[2]) && $this->mcccs[2]['et'] !== null) {
                    $texteEpreuve = '';
                    foreach ($this->mcccs[2]['et'] as $mccc) {
                        $texteEpreuve .= $this->displayTypeEpreuveWithDureePourcentage($mccc);
                    }

                    $texteEpreuve = substr($texteEpreuve, 0, -2);
                    $this->COL_MCCC_SECONDE_CHANCE_CT = $this->addQuitus($texteEpreuve, $this->isQuitus);
                }
                break;
        }
    }

    private function addQuitus(string $texteEpreuve, ?bool $hasQuitus): string
    {
        if (!str_starts_with('QUITUS', $texteEpreuve) && $hasQuitus) {
            $texteEpreuve = 'QUITUS ' . $texteEpreuve;
        }

        return $texteEpreuve;
    }

    private function displayTypeEpreuveWithDureePourcentage(\App\Entity\Mccc $mccc): string
    {
        $texte = '';
        foreach ($mccc->getTypeEpreuve() as $type) {
            if ($type !== "" && $this->typeEpreuves[$type] !== null) {
                $duree = '';
                if ($this->typeEpreuves[$type]->isHasDuree() === true) {
                    $duree = ' ' . $this->displayDuree($mccc->getDuree());
                }

                $texte .= $this->typeEpreuves[$type]->getSigle() . $duree . ' (' . $mccc->getPourcentage() . '%); ';
            } else {
                $texte .= 'erreur épreuve; ';
            }
        }

        return $texte;
    }

    protected function displayDuree(?DateTimeInterface $duree): string
    {
        if ($duree === null) {
            return '';
        }

        return $duree->format('H\hi');
    }

    protected function displayTypeEpreuveWithDureePourcentageTp(\App\Entity\Mccc $mccc, float $pourcentage, float $facteur = 1): string
    {
        $texte = '';
        foreach ($mccc->getTypeEpreuve() as $type) {
            if ($type !== "" && $this->typeEpreuves[$type] !== null) {
                $duree = '';
                if ($this->typeEpreuves[$type]->isHasDuree() === true) {
                    $duree = ' ' . $this->displayDuree($mccc->getDuree());
                }

                if ($facteur === 1.0) {
                    if (($mccc->getPourcentage() - $pourcentage) > 0.0) {
                        $texte .= $this->typeEpreuves[$type]->getSigle() . $duree . ' (' . ($mccc->getPourcentage() - $pourcentage) . '%); ';
                    }
                } else {
                    if (($facteur * $mccc->getPourcentage()) > 0.0) {
                        $texte .= $this->typeEpreuves[$type]->getSigle() . $duree . ' (' . ($facteur * $mccc->getPourcentage()) . '%); ';
                    }
                }
            } else {
                $texte .= 'erreur épreuve; ';
            }
        }

        return $texte;
    }

    public function toArray(): array
    {
        return [
            'COL_MCCC_CC' => $this->COL_MCCC_CC,
            'COL_MCCC_CCI' => $this->COL_MCCC_CCI,
            'COL_MCCC_CT' => $this->COL_MCCC_CT,
            'COL_MCCC_SECONDE_CHANCE_CC_AVEC_TP' => $this->COL_MCCC_SECONDE_CHANCE_CC_AVEC_TP,
            'COL_MCCC_SECONDE_CHANCE_CC_SANS_TP' => $this->COL_MCCC_SECONDE_CHANCE_CC_SANS_TP,
            'COL_MCCC_SECONDE_CHANCE_CC_SUP_10' => $this->COL_MCCC_SECONDE_CHANCE_CC_SUP_10,
            'COL_MCCC_SECONDE_CHANCE_CT' => $this->COL_MCCC_SECONDE_CHANCE_CT,
        ];
    }
}
