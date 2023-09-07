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

        $messages = [];

        $foldersToRemove = [
            'ebook',
            'checkaccesslink',
            'fontautoinstall',
            'multideletepages',
            'tabdyn'
        ];
        foreach($foldersToRemove as $folderName){
            if (file_exists("tools/$folderName")){
                if ($this->shouldDeactivateInsteadOfDeleting($folderName)){
                    if (is_file("tools/$folderName/desc.xml")){
                        $messages[] = $this->isActive($folderName)
                            ? (
                                "ℹ️ Deactivating folder <em>tools/$folderName</em>... "
                                .(
                                    $this->deactivate($folderName)
                                    ? '✅ Done !'
                                    : '❌ Error : not deactivated !'
                                )
                            )
                            : "ℹ️ Folder <em>tools/$folderName</em> already deactived !";
                    } else {
                        $messages[] = "❌ Error <em>tools/$folderName</em> can not be deactivated : remove it manually !";
                    }
                } else {
                    $messages[] = "ℹ️ Removing folder <em>tools/$folderName</em>... "
                        .(
                            $this->deleteTool($folderName)
                            ? '✅ Done !'
                            : '❌ Error : not deleted !'
                        );
                }
            }
        }

        if (!empty($messages)){
            $message = implode('<br/>',$messages);
            $output = <<<HTML
            <strong>Extension Maintenance</strong><br/>
            $message<br/>
            <hr/>
            HTML;

            // set output
            $this->output = str_replace(
                '<!-- end handler /update -->',
                $output.'<!-- end handler /update -->',
                $this->output
            );
        }
    }

    /**
     * test if on Windows and prefer deactive to prevent git folder to be deleted
     */
    protected function shouldDeactivateInsteadOfDeleting(string $folderName): bool
    {
        return (DIRECTORY_SEPARATOR === '\\' && is_dir("tools/$folderName") && is_dir("tools/$folderName/.git"));
    }

    protected function isActive(string $folderName): bool
    {
        $info = $this->getInfoFromDesc($folderName);
        return  empty($info['active']) ? false : in_array($info['active'], [1,"1",true,"true"]);

    }

        /**
     * retrieve info from desc file for tools
     * @param string $dirName
     * @return array
     */
    protected function getInfoFromDesc(string $dirName)
    {
        include_once 'includes/YesWikiPlugins.php';
        $pluginService = new Plugins('tools/');
        if (is_file("tools/$dirName/desc.xml")) {
            return $pluginService->getPluginInfo("tools/$dirName/desc.xml");
        }
        return [];
    }

    protected function deactivate(string $dirName): bool
    {
        $xmlPath = "tools/$dirName/desc.xml";
        if (is_file($xmlPath)) {
            $xml = file_get_contents($xmlPath);
            $newXml = preg_replace("/(active=)\"([^\"]+)\"/", "$1\"".($status ? "1" : "0")."\"", $xml);
            if (!empty($newXml) && $newXml != $xml) {
                file_put_contents($xmlPath, $newXml);
                return !$this->isActive($dirName);
            }
        }
        return false;
    }
    protected function deleteTool(string $dirName): bool
    {
        return (!$this->delete("tools/$dirName"))
            ? false
            : !file_exists("tools/$dirName");
    }

    protected function delete($path)
    {
        if (empty($path)) {
            return false;
        }
        if (is_file($path)) {
            if (unlink($path)) {
                return true;
            }
            return false;
        }
        if (is_dir($path)) {
            return $this->deleteFolder($path);
        }
    }

    private function deleteFolder($path)
    {
        $file2ignore = array('.', '..');
        if (is_link($path)) {
            unlink($path);
        } else {
            if ($res = opendir($path)) {
                while (($file = readdir($res)) !== false) {
                    if (!in_array($file, $file2ignore)) {
                        $this->delete($path . '/' . $file);
                    }
                }
                closedir($res);
            }
            rmdir($path);
        }
        return true;
    }
}
