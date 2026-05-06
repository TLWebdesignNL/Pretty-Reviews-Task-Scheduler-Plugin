<?php

/**
 * @package         Joomla.Plugins
 * @subpackage      Task.PrettyReviews
 *
 * @copyright   (C) 2025 Tom van der Laan - TLWebdesign. <https://www.tlwebdesign.nl>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Task\Prettyreviews\Extension;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\HelperFactoryInterface;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Table\Module as ModuleTable;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status as TaskStatus;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\SubscriberInterface;

\defined('_JEXEC') or die;

/**
 * Task plugin with routines that offer checks on files.
 * At the moment, offers a single routine to check and resize image files in a directory.
 *
 * @since  4.1.0
 */
final class Prettyreviews extends CMSPlugin implements SubscriberInterface
{
    use TaskPluginTrait;

    /**
     * @var string[]
     *
     * @since 4.1.0
     */
    protected const TASKS_MAP = [
        'Prettyreviews.prettyreviews' => [
            'langConstPrefix' => 'PLG_TASK_PRETTYREVIEWS_UPDATEREVIEWS',
            'form'            => 'prettyreviews',
            'method'          => 'updateReviews',
        ],
    ];

    /**
     * @inheritDoc
     *
     * @return string[]
     *
     * @since 4.1.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList' => 'advertiseRoutines',
            'onExecuteTask' => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm',
        ];
    }

    /**
     * @var boolean
     * @since 4.1.0
     */
    protected $autoloadLanguage = true;

    /**
     * Constructor.
     *
     * @param   DispatcherInterface  $dispatcher  The dispatcher
     * @param   array                $config      An optional associative array of configuration settings
     *
     * @since   4.2.0
     */
    public function __construct(DispatcherInterface $dispatcher, array $config)
    {
        parent::__construct($dispatcher, $config);
    }

    /**
     * @param   ExecuteTaskEvent  $event  The onExecuteTask event
     *
     * Deletes files older than the specified number of days from the specified folder.
     *
     * @return integer  The exit code
     *
     * @throws \RuntimeException
     * @throws LogicException
     *
     * @since 4.1.0
     */
    protected function updateReviews(ExecuteTaskEvent $event): int
    {
        $params    = $event->getArgument('params');
        $moduleIds = $params->moduleid ?? [];

        if (!is_array($moduleIds)) {
            $moduleIds = [$moduleIds];
        }

        $moduleIds = array_values(array_unique(array_filter(array_map('intval', $moduleIds))));

        if ($moduleIds === []) {
            $this->logTask('Error: Missing Pretty Reviews module ID.', 'error');
            return TaskStatus::NO_RUN;
        }

        $db     = Factory::getContainer()->get(DatabaseInterface::class);
        $failed = false;

        try {
            $moduleExtension = Factory::getApplication()->bootModule('mod_prettyreviews', 'site');

            if (!$moduleExtension instanceof HelperFactoryInterface) {
                $this->logTask('Error: Pretty Reviews module helper factory is unavailable.', 'error');
                return TaskStatus::KNOCKOUT;
            }

            $helper = $moduleExtension->getHelper('PrettyreviewsHelper');

            if ($helper === null || !method_exists($helper, 'refreshFromGoogle')) {
                $this->logTask('Error: Pretty Reviews 1.2.0 or newer is required.', 'error');
                return TaskStatus::KNOCKOUT;
            }

            foreach ($moduleIds as $moduleId) {
                $module = new ModuleTable($db);

                if (!$module->load($moduleId)) {
                    $this->logTask('Error: Module ' . $moduleId . ' was not found.', 'error');
                    $failed = true;
                    continue;
                }

                if ($module->module !== 'mod_prettyreviews' || (int) $module->client_id !== 0) {
                    $this->logTask('Error: Module ' . $moduleId . ' is not Pretty Reviews.', 'error');
                    $failed = true;
                    continue;
                }

                $this->logTask('Fetching reviews for moduleId ' . $moduleId, 'info');

                if (!$helper->refreshFromGoogle($moduleId)) {
                    $this->logTask('Error: Pretty Reviews did not write the review cache for moduleId ' . $moduleId . '.', 'error');
                    $failed = true;
                    continue;
                }

                $this->logTask('Success: Reviews have been updated for moduleId ' . $moduleId . '.', 'info');
            }
        } catch (\Throwable $e) {
            $this->logTask('Error: ' . $e->getMessage(), 'error');
            return TaskStatus::KNOCKOUT;
        }

        if ($failed) {
            return TaskStatus::KNOCKOUT;
        }

        $this->logTask('Completed updating reviews for ' . count($moduleIds) . ' module(s).', 'info');

        return TaskStatus::OK;
    }
}
