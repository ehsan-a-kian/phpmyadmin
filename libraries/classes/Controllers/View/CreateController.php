<?php

declare(strict_types=1);

namespace PhpMyAdmin\Controllers\View;

use PhpMyAdmin\Controllers\AbstractController;
use PhpMyAdmin\Controllers\Table\StructureController;
use PhpMyAdmin\Core;
use PhpMyAdmin\DatabaseInterface;
use PhpMyAdmin\Html\Generator;
use PhpMyAdmin\Http\ServerRequest;
use PhpMyAdmin\Message;
use PhpMyAdmin\ResponseRenderer;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use PhpMyAdmin\SqlParser\TokensList;
use PhpMyAdmin\Template;
use PhpMyAdmin\Url;
use PhpMyAdmin\Util;

use function __;
use function array_merge;
use function explode;
use function htmlspecialchars;
use function in_array;
use function sprintf;
use function str_contains;
use function substr;

/**
 * Handles creation of VIEWs.
 */
class CreateController extends AbstractController
{
    /** @var DatabaseInterface */
    private $dbi;

    public function __construct(ResponseRenderer $response, Template $template, DatabaseInterface $dbi)
    {
        parent::__construct($response, $template);
        $this->dbi = $dbi;
    }

    public function __invoke(ServerRequest $request): void
    {
        $this->checkParameters(['db']);
        $GLOBALS['text_dir'] = $GLOBALS['text_dir'] ?? null;
        $GLOBALS['urlParams'] = $GLOBALS['urlParams'] ?? null;
        $GLOBALS['view_algorithm_options'] = $GLOBALS['view_algorithm_options'] ?? null;
        $GLOBALS['view_with_options'] = $GLOBALS['view_with_options'] ?? null;
        $GLOBALS['view_security_options'] = $GLOBALS['view_security_options'] ?? null;

        $GLOBALS['message'] = $GLOBALS['message'] ?? null;
        $GLOBALS['sep'] = $GLOBALS['sep'] ?? null;
        $GLOBALS['arr'] = $GLOBALS['arr'] ?? null;
        $GLOBALS['view_columns'] = $GLOBALS['view_columns'] ?? null;
        $GLOBALS['column_map'] = $GLOBALS['column_map'] ?? null;
        $GLOBALS['systemDb'] = $GLOBALS['systemDb'] ?? null;
        $GLOBALS['pma_transformation_data'] = $GLOBALS['pma_transformation_data'] ?? null;
        $GLOBALS['new_transformations_sql'] = $GLOBALS['new_transformations_sql'] ?? null;
        $GLOBALS['view'] = $GLOBALS['view'] ?? null;
        $GLOBALS['item'] = $GLOBALS['item'] ?? null;
        $GLOBALS['parts'] = $GLOBALS['parts'] ?? null;

        $GLOBALS['errorUrl'] = Util::getScriptNameForOption($GLOBALS['cfg']['DefaultTabDatabase'], 'database');
        $GLOBALS['errorUrl'] .= Url::getCommon(['db' => $GLOBALS['db']], '&');

        if (! $this->hasDatabase()) {
            return;
        }

        $GLOBALS['urlParams']['goto'] = Url::getFromRoute('/table/structure');
        $GLOBALS['urlParams']['back'] = Url::getFromRoute('/view/create');

        $GLOBALS['view_algorithm_options'] = [
            'UNDEFINED',
            'MERGE',
            'TEMPTABLE',
        ];

        $GLOBALS['view_with_options'] = [
            'CASCADED',
            'LOCAL',
        ];

        $GLOBALS['view_security_options'] = [
            'DEFINER',
            'INVOKER',
        ];

        /** @var array|null $view */
        $view = $request->getParsedBodyParam('view');

        // View name is a compulsory field
        if (empty($view['name'])) {
            $GLOBALS['message'] = Message::error(__('View name can not be empty!'));
            $this->response->addJSON('message', $GLOBALS['message']);
            $this->response->setRequestStatus(false);

            return;
        }

        $createview = $request->hasBodyParam('createview');
        $alterview = $request->hasBodyParam('alterview');
        $ajaxdialog = $request->hasBodyParam('ajax_dialog');

        if ($createview || $alterview) {
            /**
             * Creates the view
             */
            $GLOBALS['sep'] = "\r\n";

            if ($createview) {
                $GLOBALS['sql_query'] = 'CREATE';
                if (isset($view['or_replace'])) {
                    $GLOBALS['sql_query'] .= ' OR REPLACE';
                }
            } else {
                $GLOBALS['sql_query'] = 'ALTER';
            }

            if (
                isset($view['algorithm'])
                && in_array($view['algorithm'], $GLOBALS['view_algorithm_options'])
            ) {
                $GLOBALS['sql_query'] .= $GLOBALS['sep'] . ' ALGORITHM = ' . $view['algorithm'];
            }

            if (! empty($view['definer'])) {
                if (! str_contains($view['definer'], '@')) {
                    $GLOBALS['sql_query'] .= $GLOBALS['sep'] . 'DEFINER='
                        . Util::backquote($view['definer']);
                } else {
                    $GLOBALS['arr'] = explode('@', $view['definer']);
                    $GLOBALS['sql_query'] .= $GLOBALS['sep'] . 'DEFINER=' . Util::backquote($GLOBALS['arr'][0]);
                    $GLOBALS['sql_query'] .= '@' . Util::backquote($GLOBALS['arr'][1]) . ' ';
                }
            }

            if (
                isset($view['sql_security'])
                && in_array($view['sql_security'], $GLOBALS['view_security_options'])
            ) {
                $GLOBALS['sql_query'] .= $GLOBALS['sep'] . ' SQL SECURITY '
                    . $view['sql_security'];
            }

            $GLOBALS['sql_query'] .= $GLOBALS['sep'] . ' VIEW '
                . Util::backquote($view['name']);

            if (! empty($view['column_names'])) {
                $GLOBALS['sql_query'] .= $GLOBALS['sep'] . ' (' . $view['column_names'] . ')';
            }

            $GLOBALS['sql_query'] .= $GLOBALS['sep'] . ' AS ' . $view['as'];

            if (isset($view['with']) && in_array($view['with'], $GLOBALS['view_with_options'])) {
                $GLOBALS['sql_query'] .= $GLOBALS['sep'] . ' WITH ' . $view['with'] . '  CHECK OPTION';
            }

            if (! $this->dbi->tryQuery($GLOBALS['sql_query'])) {
                if (! $ajaxdialog) {
                    $GLOBALS['message'] = Message::rawError($this->dbi->getError());

                    return;
                }

                $this->response->addJSON(
                    'message',
                    Message::error(
                        '<i>' . htmlspecialchars($GLOBALS['sql_query']) . '</i><br><br>'
                        . $this->dbi->getError()
                    )
                );
                $this->response->setRequestStatus(false);

                return;
            }

            // If different column names defined for VIEW
            $GLOBALS['view_columns'] = [];
            if (isset($view['column_names'])) {
                $GLOBALS['view_columns'] = explode(',', $view['column_names']);
            }

            $GLOBALS['column_map'] = $this->dbi->getColumnMapFromSql($view['as'], $GLOBALS['view_columns']);

            $GLOBALS['systemDb'] = $this->dbi->getSystemDatabase();
            $GLOBALS['pma_transformation_data'] = $GLOBALS['systemDb']->getExistingTransformationData($GLOBALS['db']);

            if ($GLOBALS['pma_transformation_data'] !== false) {
                // SQL for store new transformation details of VIEW
                $GLOBALS['new_transformations_sql'] = $GLOBALS['systemDb']->getNewTransformationDataSql(
                    $GLOBALS['pma_transformation_data'],
                    $GLOBALS['column_map'],
                    $view['name'],
                    $GLOBALS['db']
                );

                // Store new transformations
                if ($GLOBALS['new_transformations_sql'] != '') {
                    $this->dbi->tryQuery($GLOBALS['new_transformations_sql']);
                }
            }

            unset($GLOBALS['pma_transformation_data']);

            if ($ajaxdialog) {
                $GLOBALS['message'] = Message::success();
                /** @var StructureController $controller */
                $controller = Core::getContainerBuilder()->get(StructureController::class);
                $controller($request);
            } else {
                $this->response->addJSON(
                    'message',
                    Generator::getMessage(
                        Message::success(),
                        $GLOBALS['sql_query']
                    )
                );
                $this->response->setRequestStatus(true);
            }

            return;
        }

        $GLOBALS['sql_query'] = $request->getParsedBodyParam('sql_query', '');

        // prefill values if not already filled from former submission
        $GLOBALS['view'] = [
            'operation' => 'create',
            'or_replace' => '',
            'algorithm' => '',
            'definer' => '',
            'sql_security' => '',
            'name' => '',
            'column_names' => '',
            'as' => $GLOBALS['sql_query'],
            'with' => '',
        ];

        // Used to prefill the fields when editing a view
        if (isset($_GET['db'], $_GET['table'])) {
            $GLOBALS['item'] = $this->dbi->fetchSingleRow(
                sprintf(
                    "SELECT `VIEW_DEFINITION`, `CHECK_OPTION`, `DEFINER`,
            `SECURITY_TYPE`
            FROM `INFORMATION_SCHEMA`.`VIEWS`
            WHERE TABLE_SCHEMA='%s'
            AND TABLE_NAME='%s';",
                    $this->dbi->escapeString($_GET['db']),
                    $this->dbi->escapeString($_GET['table'])
                )
            );
            $createView = $this->dbi->getTable($_GET['db'], $_GET['table'])
                ->showCreate();

            // CREATE ALGORITHM=<ALGORITHM> DE...
            $GLOBALS['parts'] = explode(' ', substr($createView, 17));
            $GLOBALS['item']['ALGORITHM'] = $GLOBALS['parts'][0];

            $GLOBALS['view']['operation'] = 'alter';
            $GLOBALS['view']['definer'] = $GLOBALS['item']['DEFINER'];
            $GLOBALS['view']['sql_security'] = $GLOBALS['item']['SECURITY_TYPE'];
            $GLOBALS['view']['name'] = $_GET['table'];
            $GLOBALS['view']['as'] = $GLOBALS['item']['VIEW_DEFINITION'];
            $GLOBALS['view']['with'] = $GLOBALS['item']['CHECK_OPTION'];
            $GLOBALS['view']['algorithm'] = $GLOBALS['item']['ALGORITHM'];

            // MySQL 8.0+ - issue #16194
            if (empty($GLOBALS['view']['as'])) {
                $parser = new Parser($createView);
                /**
                 * @var CreateStatement $stmt
                 */
                $stmt = $parser->statements[0];
                $GLOBALS['view']['as'] = isset($stmt->body) ? TokensList::build($stmt->body) : $GLOBALS['view']['as'];
            }
        }

        $GLOBALS['view'] = array_merge($GLOBALS['view'], $view);

        $GLOBALS['urlParams']['db'] = $GLOBALS['db'];
        $GLOBALS['urlParams']['reload'] = 1;

        $this->addScriptFiles(['sql.js']);

        echo $this->template->render('view_create', [
            'ajax_dialog' => $ajaxdialog,
            'text_dir' => $GLOBALS['text_dir'],
            'url_params' => $GLOBALS['urlParams'],
            'view' => $GLOBALS['view'],
            'view_algorithm_options' => $GLOBALS['view_algorithm_options'],
            'view_with_options' => $GLOBALS['view_with_options'],
            'view_security_options' => $GLOBALS['view_security_options'],
        ]);
    }
}
