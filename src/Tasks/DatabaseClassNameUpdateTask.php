<?php

namespace Dynamic\ClassNameUpdate\BuildTasks;

use Dynamic\ClassNameUpdate\MappingObject;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Dev\Debug;

/**
 * Class DatabaseClassNameUpdateTask
 * @package Dynamic\ClassNameUpdate\BuildTasks
 */
class DatabaseClassNameUpdateTask extends BuildTask
{
    /**
     * @var
     */
    private $mapping_object;

    /**
     * @var
     */
    private $mapping;

    /**
     * @var string
     */
    private static $upgrade_file_path;

    /**
     * @var string
     */
    private static $segment = 'database-classname-update-task';

    /**
     * @var string
     */
    protected $title = 'Database ClassName Update Task';

    /**
     * @var string
     */
    protected $description = "Update ClassName data for a SilverStripe 3 to SilverStripe 4 migration. Be sure to set the absolute path to the .upgrade.yml file for this task before running it or nothing will happen.";

    /**
     * @param \SilverStripe\Control\HTTPRequest $request
     */
    public function run($request, $mapping = [])
    {
        if (empty($mapping)) {
			$update_file_path = $this->config()->get('upgrade_file_path');
            if (!$update_file_path) {
                $class = static::class;
                echo "You must specify the configuration variable: 'upgrade_file_path' for '{$class}'\n";
                return;
            }
			else {
				if (is_array($update_file_path)) {
					foreach ($update_file_path as $filePath) {
						if ($this->isFileExists($filePath)) {
							$mapping = $this->getMappingObject($filePath);
							$this->updateClassNameColumns($mapping);
						}
					}
				}
				else {
					if ($this->isFileExists($update_file_path)) {
						$mapping = $this->getMappingObject($update_file_path);
						$this->updateClassNameColumns($mapping);
					}
				}
			}
        }

		else {
			$this->updateClassNameColumns($mapping);
		}
        echo "Database ClassName data has been updated\n";
    }

    /**
     * @param $mapping
     */
    protected function updateClassNameColumns($mapping)
    {
Debug::dump($mapping);
Debug::dump($mapping === (array)$mapping);
        $mapping = ($mapping === (array)$mapping) ? $mapping : $this->getMapping();
        foreach ($mapping as $key => $val) {
            $ancestry = ClassInfo::ancestry($val);
            $ancestry = array_merge(array_values($ancestry), array_values($ancestry));

            if (in_array(DataObject::class, $ancestry)) {
                $queryClass = $ancestry[array_search(DataObject::class, $ancestry) + 1];

                foreach ($this->yieldRecords($queryClass, $key) as $record) {
                    $this->updateRecord($record, $val);
                }
            }
        }
    }

    /**
     * @param $record
     * @param $updatedClassName
     */
    protected function updateRecord($record, $updatedClassName)
    {
        if ($record instanceof SiteTree || $record->hasExtension(Versioned::class)) {
            $published = $record->isPublished();
        }

        $record->ClassName = $updatedClassName;
        $record->write();

        if (isset($published) && $published) {
            $record->publishSingle();
        }
    }

    /**
     * @return $this
     */
    public function setMappingObject($filePath)
    {
        $mapping = MappingObject::singleton();
        $mapping->setMappingPath($filePath);

        $this->mapping_object = $mapping;

        return $this;
    }

    /**
     * @return mixed
     */
    protected function getMappingObject($filePath = null)
    {
        $this->setMappingObject($filePath);
        return $this->mapping_object;
    }

    /**
     * @return $this
     */
    protected function setMapping()
    {
Debug::dump();
        $this->mapping = $this->getMappingObject()->getUpgradeMapping();

        return $this;
    }

    /**
     * @return mixed
     */
    protected function getMapping()
    {
Debug::dump(["Inside Task->getMapping(): this->mapping", $this->mapping]);
        if (!$this->mapping) {
            $this->setMapping();
        }

        return $this->mapping;
    }

    /**
     * @param $singleton
     * @param $legacyName
     * @return \Generator
     */
    public function yieldRecords($class, $legacyName)
    {
        foreach ($class::get()->filter('ClassName', $legacyName) as $object) {
            yield $object;
        }
    }

	private function isFileExists($filePath) {
		$isFileExists = true;
		if (!file_exists($filePath)) {
			$isFileExists = false;
			echo ("WARNING: $filePath does not exist, skipping\n");
		}
		return $isFileExists;
	}
}
