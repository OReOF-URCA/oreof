<?php

namespace App\MessageHandler;

use App\Classes\Mailer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use App\Message\ExportGenerique;
use App\Service\ExportGeneriqueFicheMatiere;
use App\Service\ExportGeneriqueParcours;
use DateTime;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;

#[AsMessageHandler(fromTransport: 'async_export')]
class ExportGeneriqueHandler {

    public const SUBJECT_EMAIL = 'Votre export est prêt';

    public function __construct(
        private ExportGeneriqueParcours $exportGeneriqueParcours,
        private ExportGeneriqueFicheMatiere $exportGeneriqueFicheMatiere,
        private Filesystem $fs,
        private MailerInterface $mailer
    ) {}

    public function __invoke(ExportGenerique $exportGenerique){
        // RAM disponible pour générer les fichiers
        ini_set('memory_limit', '2500M');
        // Utile lorsque le PDF prend longtemps à se charger 
        // Requête / Réponse HTTP (60s par défaut => 3 minutes)
        ini_set('default_socket_timeout', 180);

        $request = Request::create('', parameters: [
                    'id' => $exportGenerique->getParcoursIdArray(),
                    'val' => $exportGenerique->getFieldValueArray(),
                    'withFieldSorting' => $exportGenerique->getWithFieldSorting(),
                    'campagne' => $exportGenerique->getCampagneCollecte(),
                    'withHeader' => $exportGenerique->hasDefaultHeader(),
                    'predefinedTemplate' => $exportGenerique->isPredefinedTemplate(),
                    'templateName' => $exportGenerique->getPredefinedTemplateName()
                ]);
        $typeExport = $exportGenerique->getTypeExport();

        $dateNowFormat = (new DateTime())->format('d-m-Y H:i');

        if($typeExport['type'] === 'parcours'){
            $emailAttachmentName = "Export-Generique-Parcours-{$dateNowFormat}";
            if($typeExport['format'] === 'pdf'){
                $emailAttachmentName .= '.pdf';
                [$contentParcoursPdf] = $this->exportGeneriqueParcours->generatePdf($request);
                $this->sendEmailWithFile(
                    $contentParcoursPdf,
                    $exportGenerique->getEmailDestinataire(),
                    $emailAttachmentName
                );
            }
            else if ($typeExport['format'] === 'xlsx') {
                $emailAttachmentName .= '.xlsx';
                [$contentParcoursXlsx] = $this->exportGeneriqueParcours->generateXlsxSpreadsheet($request);
                $this->sendEmailWithFile(
                    $contentParcoursXlsx, 
                    $exportGenerique->getEmailDestinataire(), 
                    $emailAttachmentName
                );
            }
        }
        else if ($typeExport['type'] === 'fiche_matiere') {
            $emailAttachmentName = "Export-Generique-Fiche-Matiere-{$dateNowFormat}";
            if($typeExport['format'] === 'pdf'){
                $emailAttachmentName .= ".pdf";
                [$contentFmPdf] = $this->exportGeneriqueFicheMatiere->generatePdf($request);
                $this->sendEmailWithFile(
                    $contentFmPdf,
                    $exportGenerique->getEmailDestinataire(),
                    $emailAttachmentName
                );
            }
            else if ($typeExport['format'] === 'xlsx') {
                $emailAttachmentName .= ".xlsx";
                [$contentFmXlsx] = $this->exportGeneriqueFicheMatiere->generateXlsxSpreadsheet($request);
                $this->sendEmailWithFile(
                    $contentFmXlsx, 
                    $exportGenerique->getEmailDestinataire(),
                    $emailAttachmentName
                );
            }
        }
    }

    private function getEmailWithAttachment(string $fileAttachment, string $emailDestinataire, string $fileName){
        $email = new Email();
        return $email->from(Mailer::MAIL_GENERIC)
            ->to($emailDestinataire)
            ->subject(self::SUBJECT_EMAIL)
            ->replyTo('no-reply@univ-reims.fr')
            ->html($this->getEmailBody())
            ->addPart(new DataPart(new File($fileAttachment), $fileName));
    }

    private function getEmailBody() : string {
        return <<<HTML
        <p>
            Vous avez demandé un export depuis ORéOF, et il a été généré avec succès.
        </p>
        <p>
            Le fichier demandé est attaché en pièce jointe de ce mail.
        </p>
        <p>
            <i>Ce courriel a été généré automatiquement, merci de ne pas y répondre.</i>
        </p>
HTML;
    }

    private function createTemporaryAttachment(string $fileContent){
        $tmp = $this->fs->tempnam(__DIR__ . '/../../public/temp/', 'export_generique');
        $this->fs->appendToFile($tmp, $fileContent);

        return $tmp;
    }

    private function sendEmailWithFile(string $fileContent, string $emailDestinataire, string $fileName){
        $tmpFile = $this->createTemporaryAttachment($fileContent);
        $email = $this->getEmailWithAttachment($tmpFile, $emailDestinataire, $fileName);
        $this->mailer->send($email);

        $this->fs->remove($tmpFile);
    }
}