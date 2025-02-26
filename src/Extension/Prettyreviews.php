<?php

/**
 * @package         Joomla.Plugins
 * @subpackage      Task.PrettyReviews
 *
 * @copyright   (C) 2025 Tom van der Laan - TLWebdesign. <https://www.tlwebdesign.nl>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Task\Prettyreviews\Extension;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Table\Table;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status as TaskStatus;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
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

        $params = $event->getArgument('params');
        $moduleId = $params->moduleid;

        $module = Table::getInstance('Module', 'JTable', []);

        if (!$module->load($moduleId)) {
            $this->logTask('Error: Module is not Pretty Reviews.', 'error');
            return TaskStatus::NO_RUN;
        }

        // Check if the module is 'mod_prettyreviews'
        if ($module && $module->module === 'mod_prettyreviews') {
            // Decode module params
            $params = json_decode($module->params, true);

            // Extract required parameters
            $cid = $params['cid'] ?? null;
            $apiKey = $params['apikey'] ?? null;
            $reviewSort = $params['reviewsort'] ?? null;
            $secret = $params['secret'] ?? null;

            // Validate parameters
            if (empty($cid) || empty($apiKey) || empty($reviewSort) || empty($secret)) {
                $this->logTask('Error: Missing required parameters in Pretty Reviews module.', 'error');
                return TaskStatus::KNOCKOUT;
            } else {
                $this->logTask('Fetching reviews for moduleId ' . $moduleId, 'info');


                // Construct AJAX URL
                $joomlaRoot = Uri::root();
                $url = $joomlaRoot . 'index.php?option=com_ajax&module=prettyreviews&method=updateGoogleReviews&format=json'
                    . '&moduleId=' . urlencode($moduleId)
                    . '&cid=' . urlencode($cid)
                    . '&apiKey=' . urlencode($apiKey)
                    . '&reviewSort=' . urlencode($reviewSort)
                    . '&secret=' . urlencode($secret);

                // Initialize HTTP client
                $http = HttpFactory::getHttp();

                try {
                    // Make GET request
                    $response = $http->get($url);

                    // Decode JSON response
                    $data = json_decode($response->body, true);

                    // Check if update was successful
                    if (!empty($data['data']) && $data['data'] === true) {
                        $this->logTask('Success: Reviews have been updated!', 'info');
                    } else {
                        $this->logTask('Error: Something went wrong with the AJAX request!', 'error');
                        return TaskStatus::KNOCKOUT;
                    }
                } catch (Exception $e) {
                    $this->logTask('Error: ' . $e->getMessage(), 'error');
                    return TaskStatus::KNOCKOUT;

                }
            }
        } else {
            $this->logTask('Error: Module is not Pretty Reviews.', 'error');
            return TaskStatus::NO_RUN;
        }

        $this->logTask('Completed updating reviews for moduleId ' . $moduleId, 'info');

        return TaskStatus::OK;
    }
}
