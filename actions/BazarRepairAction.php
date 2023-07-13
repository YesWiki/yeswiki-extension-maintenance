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

use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Maintenance\Controller\ApiController;

class BazarRepairAction extends YesWikiAction
{
    public function run()
    {
        return $this->render("@maintenance/bazar-repair.twig", [
            'forms' => (!$this->isWikiHibernated() && $this->wiki->UserIsAdmin())
                ? $this->getService(FormManager::class)->getAll()
                : [],
            'isWikiHibernated' => $this->isWikiHibernated(),
            'restrictedFieldnames' => ApiController::RESTRICTED_FIELD_NAMES
        ]);
    }
}
