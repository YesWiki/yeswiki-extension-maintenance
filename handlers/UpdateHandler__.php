<?php

/*
 * This file is part of the YesWiki Extension maintenance.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YesWiki\Maintenance;

use Exception;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Plugins;
use YesWiki\Maintenance\Service\UpdateHandlerService;
use YesWiki\Security\Controller\SecurityController;

class UpdateHandler__ extends YesWikiHandler
{
    public function run()
    {
        if ($this->getService(SecurityController::class)->isWikiHibernated()) {
            throw new Exception(_t('WIKI_IN_HIBERNATION'));
        };
        if (!$this->wiki->UserIsAdmin()) {
            return null;
        }

        $version = $this->params->get('yeswiki_version');
        if (!is_string($version)) {
            $version = '';
        }
        $release = $this->params->get('yeswiki_release');
        if (!is_string($release)) {
            $release = '';
        }
        $matches = [];
        if (
            $version  !== 'doryphore'
            || !preg_match("/^(\d+)\.(\d+)\.(\d+)\$/", $release, $matches)
            || intval($matches[1]) > 4
            || (
                intval($matches[1]) === 4
                && (
                    intval($matches[2]) > 4
                    || (
                        intval($matches[2]) === 4
                        && intval($matches[3]) > 4
                    )
                )
            )
        ) {
            return null;
        }

        $updateHandlerService = $this->getService(UpdateHandlerService::class);

        $messages = [];
        $updateHandlerService->removeOrDeactivateATool('ebook', $messages);
        $updateHandlerService->removeOrDeactivateATool('checkaccesslink', $messages);
        $updateHandlerService->removeOrDeactivateATool('fontautoinstall', $messages);
        $updateHandlerService->removeOrDeactivateATool('multideletepages', $messages);
        $updateHandlerService->removeOrDeactivateATool('tabdyn', $messages);

        if (!empty($messages)) {
            $message = implode('<br/>', array_column($messages, 'formattedText'));
            $output = <<<HTML
            <strong>Extension Maintenance</strong><br/>
            $message<br/>
            <hr/>
            HTML;

            // set output
            $this->output = str_replace(
                '<!-- end handler /update -->',
                $output . '<!-- end handler /update -->',
                $this->output
            );
        }
    }
}
