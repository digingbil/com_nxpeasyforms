<?php

/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */


declare(strict_types=1);

\defined('_JEXEC') or die;

use Joomla\CMS\Form\Field\ModalSelectField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

/**
 * Legacy-compatible modal field loader for selecting a form.
 *
 * This class allows Joomla to find the field when rendering the menu item form
 * without relying on namespaced autoloaders. It mirrors the logic of
 * Administrator\Field\Modal\FormField.
 */
class JFormFieldModal_Form extends ModalSelectField
{
    /**
     * The form field type.
     *
     * @var string
     */
    protected $type = 'Modal_Form';

    /**
     * Initialise the field.
     *
     * @param   \SimpleXMLElement  $element  The field XML element.
     * @param   mixed              $value    The field value.
     * @param   string|null        $group    The field group.
     *
     * @return  boolean
     */
    public function setup($element, $value, $group = null)
    {
        $result = parent::setup($element, $value, $group);

        if (!$result) {
            return $result;
        }

        $link = (new Uri())->setPath(Uri::base(true) . '/index.php');
        $link->setQuery([
            'option'                => 'com_nxpeasyforms',
            'view'                  => 'forms',
            'layout'                => 'modal',
            'tmpl'                  => 'component',
            'function'              => 'Joomla.fieldModalSelect.select',
            Session::getFormToken() => 1,
        ]);

        $this->urls['select']  = (string) $link;
        $this->modalTitles['select'] = Text::_('COM_NXPEASYFORMS_SELECT_FORM');
        $this->buttonIcons['select'] = 'icon-search';

        $this->canDo['select'] = true;
        $this->canDo['new'] = false;
        $this->canDo['edit'] = false;
        $this->canDo['clear'] = true;

        $this->sql_title_table  = '#__nxpeasyforms_forms';
        $this->sql_title_column = 'title';
        $this->sql_title_key    = 'id';

        $this->hint = $this->hint ?: Text::_('COM_NXPEASYFORMS_FIELD_FORM_DESC');
        $this->dataAttributes['data-content-type'] = 'com_nxpeasyforms.form';

        return $result;
    }

    /**
     * Get the renderer for the field layout.
     *
     * @param   string  $layoutId  Layout identifier.
     *
     * @return  \Joomla\CMS\Layout\FileLayout
     */
    protected function getRenderer($layoutId = 'default')
    {
        $renderer = parent::getRenderer($layoutId);
        $renderer->setComponent('com_nxpeasyforms');
        $renderer->setClient(1);

        return $renderer;
    }
}
