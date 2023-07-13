<?php

/*
 * This file is part of the YesWiki Extension maintenance.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YesWiki\Maintenance\Controller;

use Exception;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use Symfony\Component\Security\Csrf\CsrfToken; // TODO remove 
use Symfony\Component\Security\Csrf\CsrfTokenManager; // TODO remove
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\ApiResponse;
// use YesWiki\Core\Controller\CsrfTokenController;
use YesWiki\Core\YesWikiController;
use YesWiki\Maintenance\Service\FilesService;

class ApiController extends YesWikiController
{
    public const RESTRICTED_FIELD_NAMES = [
        'bf_latitude',
        'bf_longitude',
        'date_creation_fiche',
        'date_maj_fiche',
        'html_data',
        'id_fiche',
        'id_typeannonce',
        'owner',
        'statut_fiche',
        'url',
        'user',
    ];

    /**
     * @Route("/api/files",methods={"GET"}, options={"acl":{"public","@admins"}},priority=2)
     */
    public function getFiles()
    {
        $this->denyAccessUnlessAdmin();
        $filesService = $this->getService(FilesService::class);
        $files = $filesService->getFiles();
        return new ApiResponse(['files'=>$files]);
    }

    /**
     * @Route("/api/files/check",methods={"POST"}, options={"acl":{"public","@admins"}},priority=2)
     */
    public function checkFiles()
    {
        $this->denyAccessUnlessAdmin();
        $filesService = $this->getService(FilesService::class);
        $files = $filesService->checkFiles($_POST);
        return new ApiResponse(['files'=>$files]);
    }

    /**
     * @Route("/api/files/movetotrash",methods={"POST"}, options={"acl":{"public","@admins"}},priority=2)
     */
    public function moveFilesToTrash()
    {
        $this->denyAccessUnlessAdmin();
        $filesService = $this->getService(FilesService::class);
        list('files'=>$files, 'removedFiles'=>$removedFiles) = $filesService->moveFilesToTrash($_POST);
        return new ApiResponse(['files'=>$files,'removedFiles'=>$removedFiles]);
    }

    /**
     * @Route("/api/files/restore",methods={"POST"}, options={"acl":{"public","@admins"}},priority=2)
     */
    public function restoreFiles()
    {
        $this->denyAccessUnlessAdmin();
        $filesService = $this->getService(FilesService::class);
        list('files'=>$files, 'removedFiles'=>$removedFiles) = $filesService->restoreFiles($_POST);
        return new ApiResponse(['files'=>$files,'removedFiles'=>$removedFiles]);
    }

    /**
     * @Route("/api/files/delete",methods={"POST"}, options={"acl":{"public","@admins"}},priority=2)
     */
    public function deleteFiles()
    {
        $this->denyAccessUnlessAdmin();
        $filesService = $this->getService(FilesService::class);
        list('files'=>$files, 'removedFiles'=>$removedFiles) = $filesService->restoreFiles($_POST, true);
        return new ApiResponse(['files'=>$files,'removedFiles'=>$removedFiles]);
    }
    
    /**
     * @Route("/api/forms/{formId}/renamefield", methods={"POST"},options={"acl":{"public","@admins"}})
     */
    public function renameField($formId)
    {
        $form = $this->getService(FormManager::class)->getOne($formId);
        if (!$form || !isset($form['bn_id_nature'])) {
            throw new NotFoundHttpException();
        }
        if (empty($_POST['oldname'])
            || !is_string($_POST['oldname'])){
            throw new Exception('$_POST[\'oldname\'] badly defined');
        }
        if (empty($_POST['newname'])
            || !is_string($_POST['newname'])){
            throw new Exception('$_POST[\'newname\'] badly defined');
        }
        $oldname = strval($_POST['oldname']);
        $newname = strval($_POST['newname']);

        if (in_array($oldname,self::RESTRICTED_FIELD_NAMES)){
            throw new Exception('This $_POST[\'oldname\'] value is not authorized');
        }
        if (in_array($newname,self::RESTRICTED_FIELD_NAMES)){
            throw new Exception('This $_POST[\'newname\'] value is not authorized');
        }
        $this->testToken();

        $entryManager = $this->getService(EntryManager::class);
        if (method_exists($entryManager,'renameAttributesAndReturnList')){
            $entries = $entryManager->renameAttributesAndReturnList(
                [
                    'formsIds' => [$formId]
                ],
                [
                    [$oldname => $newname]
                ],
                false 
            );
            $done = !empty($entries);
        } elseif (method_exists($entryManager,'renameAttributes')) {
            $entries = [];
            $done = $entryManager->renameAttributes(
                [
                    'formsIds' => [$formId]
                ],
                [
                    [$oldname => $newname]
                ],
                false 
            );
        } else {
            $done = false;
            $entries = [];
        }
        return new ApiResponse(compact(['done','entries']));
    }

    /**
     * @Route("/api/forms/{formId}/renamefieldinfo", methods={"POST"},options={"acl":{"public","@admins"}})
     */
    public function getRenameFieldInfo($formId)
    {
        $this->testToken();

        $form = $this->getService(FormManager::class)->getOne($formId);
        if (!$form || !isset($form['bn_id_nature'])) {
            throw new NotFoundHttpException();
        }

        $availableNewNames = [];
        $availableOldNames = [];

        foreach ($form['prepared'] as $field) {
            $propertyName = $field->getPropertyName();
            if (!empty($propertyName) && !in_array($propertyName,$availableNewNames)){
                $availableNewNames[] = $propertyName;
            }
        }

        $entries = $this->getService(EntryManager::class)->search([
            'formsIds' => [
                $form['bn_id_nature']
            ]
        ]);

        if (!empty($entries)){
            foreach($entries as $entry){
                foreach($entry as $key => $value){
                    if (!empty($key) 
                        && is_string($key)
                        && !in_array($key,self::RESTRICTED_FIELD_NAMES)
                        && !in_array($key,$availableNewNames)
                        && !in_array($key,$availableOldNames)){
                        $availableOldNames[] = $key;
                    }
                }
            }
        }
        return new ApiResponse(compact(['availableNewNames','availableOldNames']));
    }

    /**
     * backup test anti-crsf token waiting PR
     * TODO remove it
     * @throws TokenNotFoundException
     */
    protected function testToken()
    {
        // $csrfTokenController = $this->getService(CsrfTokenController::class);
        // $csrfTokenController->checkToken('main', 'POST', 'token',false);

        $inputToken = filter_input(INPUT_POST, 'token', FILTER_UNSAFE_RAW);
        $inputToken = in_array($inputToken,[false,null],true) ? $inputToken : htmlspecialchars(strip_tags($inputToken));
        if (is_null($inputToken) || $inputToken === false) {
            throw new TokenNotFoundException(_t('NO_CSRF_TOKEN_ERROR'));
        }
        $token = new CsrfToken('main', $inputToken);
        $csrfTokenManager = $this->getService(CsrfTokenManager::class);
        $isValid = $csrfTokenManager->isTokenValid($token);
        if (!$isValid) {
            throw new TokenNotFoundException(_t('CSRF_TOKEN_FAIL_ERROR'));
        }
    }

}
