Module Settings Page
====================

A settings page is created by extending ``\extension\ka_extensions\ControllerSettings``.
The class is also the module installer (it extends ``ControllerInstaller``), so database tables
of the module are defined in ``onLoad()``:

.. code-block:: php

    namespace mycomp\mymodule;

    class ControllerMyModule extends \extension\ka_extensions\ControllerSettings {

        protected $ext_code = 'mycomp/mymodule';
        protected $extension_version = '1.0.0.0';

        protected function onLoad() {
            $this->tables = array();
            return parent::onLoad();
        }

        protected function getFields() {
            return array(
                'mymodule_default_group_id' => array(
                    'code'          => 'mymodule_default_group_id',
                    'type'          => 'select',
                    'default_value' => '',
                    'options'       => array(),
                ),
                'mymodule_limit' => array(
                    'code'          => 'mymodule_limit',
                    'type'          => 'number',
                    'required'      => true,
                    'min_value'     => 1,
                ),
            );
        }
    }

    class_alias(__NAMESPACE__ . '\ControllerMyModule', 'ControllerExtensionMyCompMyModule');

The page shows the fields, validates them on submit and saves them as store settings.
Default values are saved on install (saved values are kept). The settings stay in the database after
uninstall, see :doc:`upgrading_modules`.

Field Format
------------

``code``
    Setting key. When it is missing, the array key is used.
``type``
    Field template from ``common/fields/``: ``text`` (default), ``textarea``, ``htmlarea``, ``number``,
    ``select``, ``checkbox``, ``checkboxes``, ``radio``, ``date``, ``datetime``, ``time``, ``email``,
    ``tel``, ``password``, ``image``, ``file``, ``hidden``, ``plaintext``...
``default_value``
    Value saved on install and shown when the setting is empty.
``options``
    Options of ``select``, ``radio``, ``checkboxes``.
``title``
    Field title. Without it the language variable ``txt_title_<code>`` is used.
``required``, ``min_value``, ``max_value``
    Validation rules.

Form Fields (``TraitControllerForm``)
-------------------------------------

The same field format is used by other forms through ``\extension\ka_extensions\TraitControllerForm``:

- ``getFieldsWithData($fields, $old_data, $new_data, $errors)`` - fills fields with values
  (posted data first, then old data, then ``default_value``);
- ``getFieldValues($fields)`` - returns ``code => value`` pairs for saving;
- ``validateFields($fields, $post)`` - checks the rules, errors are stored in ``$this->errors``;
- ``validateLanguageFields()`` / ``getLanguageFieldsWithData()`` - the same for multilingual fields.

Custom Template
---------------

To show your own template, override ``index()``:

.. code-block:: php

    public function index() {
        $this->disableRender();
        parent::index();
        $this->enableRender();

        $this->data['is_ka_smethods_installed'] = \KaGlobal::isKaInstalled('ka_smethods');

        $this->showPage('extension/mycomp/mymodule/settings');
    }

The template extends the standard settings page and replaces the ``settings_contents`` block:

.. code-block:: twig

    {% extends '@ka_common/pages/settings.twig' %}

    {% block settings_contents %}
        ...your html...
    {% endblock %}
