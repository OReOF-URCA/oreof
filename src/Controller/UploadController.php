<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/UploadController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 23/03/2026 11:16
 */

declare(strict_types=1);

namespace App\Controller;

use App\Exception\FileUploadException;
use App\Service\SecureUploadService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class UploadController extends BaseController
{
    #[Route('/download/{context}/{filename}', name: 'app_upload_download', requirements: ['context' => 'conseils'])]
    public function download(
        string              $context,
        string              $filename,
        Request             $request,
        SecureUploadService $secureUploadService,
    ): BinaryFileResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        try {
            $path = $secureUploadService->resolveStoredFilePath($context, $filename);
        } catch (FileUploadException) {
            throw new NotFoundHttpException();
        }

        if (!is_file($path)) {
            throw new NotFoundHttpException();
        }

        $downloadName = $secureUploadService->getDownloadFilename($request->query->getString('originalName', ''), $filename);

        return $this->file($path, $downloadName, ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }
}


