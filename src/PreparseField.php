<?php
/**
 * Preparse Field plugin for Craft CMS 3.x
 *
 * @link      https://www.vaersaagod.no
 * @copyright Copyright (c) 2017 André Elvan
 */

namespace aelvan\preparsefield;

use aelvan\preparsefield\fields\PreparseFieldType;
use aelvan\preparsefield\services\PreparseFieldService as PreparseFieldServiceService;

use Craft;
use craft\base\Plugin;
use craft\events\ElementEvent;
use craft\events\MoveElementEvent;
use craft\services\Elements;
use craft\services\Fields;
use craft\events\RegisterComponentTypesEvent;

use craft\services\Structures;
use yii\base\Event;

/**
 * Preparse field plugin
 *
 * @author    André Elvan
 * @package   PreparseField
 * @since     1.0.0
 *
 * @property  PreparseFieldServiceService $preparseFieldService
 */
class PreparseField extends Plugin
{
    /**
     * Static property that is an instance of this plugin class so that it can be accessed via
     * PreparseField::$plugin
     *
     * @var PreparseField
     */
    public static $plugin;

    /**
     * Stores the IDs of elements we already preparsed the fields for.
     *
     * @var array
     */
    public $preparsedElements;

    /**
     *  Plugin init method
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        $this->preparsedElements = [
            'onBeforeSave' => [],
            'onSave' => [],
            'onMoveElement' => [],
        ];

        // Register our fields
        Event::on(Fields::class, Fields::EVENT_REGISTER_FIELD_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = PreparseFieldType::class;
            }
        );

        // Before save element event handler
        Event::on(Elements::class, Elements::EVENT_BEFORE_SAVE_ELEMENT,
            function(ElementEvent $event) {
                $element = $event->element;

                if (!\in_array($element->id, $this->preparsedElements['onBeforeSave'], true)) {
                    $this->preparsedElements['onBeforeSave'][] = $element->id;

                    $content = self::$plugin->preparseFieldService->getPreparseFieldsContent($element, 'onBeforeSave');

                    if (!empty($content)) {
                        $element->setFieldValues($content);
                    }
                }
            }
        );

        // After save element event handler
        Event::on(Elements::class, Elements::EVENT_AFTER_SAVE_ELEMENT,
            function(ElementEvent $event) {
                $element = $event->element;

                if (!\in_array($element->id, $this->preparsedElements['onSave'], true)) {
                    $this->preparsedElements['onSave'][] = $element->id;

                    $content = self::$plugin->preparseFieldService->getPreparseFieldsContent($element, 'onSave');

                    if (!empty($content)) {
                        $element->setFieldValues($content);
                        $success = Craft::$app->getElements()->saveElement($element);

                        // if no success, log error
                        if (!$success) {
                            Craft::error('Couldn’t save element with id “'.$element->id.'”', __METHOD__);
                        }
                    }
                }
            }
        );

        // After move element event handler
        Event::on(Structures::class, Structures::EVENT_AFTER_MOVE_ELEMENT,
            function(MoveElementEvent $event) {
                $element = $event->element;

                if (self::$plugin->preparseFieldService->shouldParseElementOnMove($element) && !\in_array($element->id, $this->preparsedElements['onMoveElement'], true)) {
                    $this->preparsedElements['onMoveElement'][] = $element->id;

                    $success = Craft::$app->getElements()->saveElement($element);

                    // if no success, log error
                    if (!$success) {
                        Craft::error('Couldn’t move element with id “'.$element->id.'”', __METHOD__);
                    }
                }
            }
        );
    }
}