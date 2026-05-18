<?php

namespace App\Command;

use App\Service\ApiJsonExport;
use App\Service\LheoXML;
use DateTime;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(
    name: 'app:api-json-versioning',
    description: "Commande pour gérer l'API JSON du versioning",
)]
class ApiJsonVersioningCommand extends Command
{
    private Filesystem $fs;

    private ApiJsonExport $apiJsonExport;

    private ParameterBagInterface $parameterBag;

    private LheoXML $lheoXml;

    public function __construct(
        Filesystem $fs,
        ApiJsonExport $apiJsonExport,
        ParameterBagInterface $parameterBag,
        LheoXML $lheoXml
    )
    {
        parent::__construct();
        $this->fs = $fs;
        $this->apiJsonExport = $apiJsonExport;
        $this->parameterBag = $parameterBag;
        $this->lheoXml = $lheoXml;
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                name: 'generate-index-api',
                mode: InputOption::VALUE_NONE,
                description: "Génère le fichier d'index pour l'API JSON du versioning"
            )->addOption(
                name: 'version-two',
                mode: InputOption::VALUE_NONE,
                description: "Génère l'API pour la version 2"
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $generateIndexApi = $input->getOption('generate-index-api');
        $isV2 = $input->getOption('version-two');

        if($generateIndexApi){
            ini_set('memory_limit', '2500M');
            ini_set('max_execution_time', '300');

            $apiV2 = false;
            if($isV2){
                $filename = "api_json_urca_versioning_v2.json";
                $path = __DIR__ . "/../../public/api_json_v2/";
                $apiV2 = true;
            } else {
                $filename = "api_json_urca_versioning.json";
                $path = __DIR__ . "/../../public/api_json/";
            }

            try {
                $hostname = $this->parameterBag->get('APP_HOSTNAME');
            } catch (Exception $e) {
                $io->warning("Définissez la variable APP_HOSTNAME pour continuer (.env)");
                return Command::INVALID;
            }

            if($isV2){
                $io->writeln("Génération de l'index de l'API JSON (V2) en cours...");
            } else {
                $io->writeln("Génération de l'index de l'API JSON en cours...");
            }

            if($this->fs->exists($path . $filename)){
                $now = (new DateTime())->format('d-m-Y_H-i');
                $this->fs->rename($path . $filename, $path . $now . "-" .  $filename);
            }
            $apiJson = $this->apiJsonExport->generateApiVersioning($hostname, $io, $this->lheoXml, $apiV2);
            $this->fs->appendToFile($path . $filename, json_encode($apiJson));

            if($isV2){
                $io->success("Index de l'API JSON (versioning) V2 généré !");
            } else {
                $io->success("Index de l'API JSON (versioning) créé avec succès !");
            }
            return Command::SUCCESS;
        }


        return Command::SUCCESS;
    }
}
