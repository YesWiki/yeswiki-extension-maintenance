<?php

use YesWiki\Maintenance\Service\UpdateHandlerService;
use YesWiki\Core\YesWikiMigration;

class RemoveToolCheckaccesslink extends YesWikiMigration
{
    public function run()
    {
        $updateHandlerService = $this->wiki->services->get(UpdateHandlerService::class);
        $messages = [];
        $updateHandlerService->removeOrDeactivateATool('checkaccesslink', $messages);
        $errors = array_column(
            array_filter(
                $messages,
                function ($message) {
                    return $message['status'] != 'ok';
                }
            ),
            'text'
        );
        if (!empty($errors)) {
            throw new Exception('Error Processing ' . implode('|', $errors));
        }
    }
}
