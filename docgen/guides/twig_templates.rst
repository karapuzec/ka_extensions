Twig Templates
==============

Base Page Templates
-------------------

Ka Extensions provides base templates that can be extended with ``extends``/``block``
(see `Twig documentation <https://twig.symfony.com/doc/2.x/>`_):

::

    admin/view/template/extension/ka_extensions/common/pages/page.twig
    admin/view/template/extension/ka_extensions/common/pages/settings.twig
    admin/view/template/extension/ka_extensions/common/pages/form.twig
    catalog/view/theme/default/template/extension/ka_extensions/common/page.twig

They are available with the ``@ka_common`` namespace. The admin page template defines the blocks
``page_buttons`` and ``page_contents``:

.. code-block:: twig

    {% extends '@ka_common/pages/page.twig' %}

    {% block page_buttons %}
        <a href="{{ action_cancel }}" class="btn btn-default">{{ t('Back') }}</a>
    {% endblock %}

    {% block page_contents %}
        <p>My page</p>
    {% endblock %}

Template Inheritance in Kamod
-----------------------------

When a module overrides a template (see :doc:`overriding_core_files`), kamod builds a chain of
templates where every template extends the previous one.

A template **without blocks** is wrapped in the ``parent_template`` block automatically.
The original template (the base of the chain) is wrapped even when it has its own blocks, so a
module template without blocks replaces the whole template, and the blocks of the original template
can still be overridden by other modules. The original template is not wrapped when it defines
``parent_template`` itself or uses ``extends``, ``use``, ``macro``, ``import`` or ``from``.
To add HTML to such a template, call ``parent()``:

.. code-block:: twig

    my new html
    {{ parent() }}

It is better to put your HTML into your own blocks, then other modules can change them too.
In that case define the ``parent_template`` block explicitly:

.. code-block:: twig

    {% block parent_template %}
        {% block my_module_html %}
            my new html
        {% endblock %}
        {{ parent() }}
    {% endblock %}

A module template replacing the whole page should keep the blocks of the original template that
other modules override, e.g. ``product_info_extra`` of a theme product page. Call ``parent()`` in
such a block: then the block of other modules is shown whatever the order of the modules is
(a block of the outer template wins, ``parent()`` passes it to the inner templates):

.. code-block:: twig

    {% block parent_template %}
        <div class="my-layout">
            <ul class="list-unstyled">
                {% block product_info_extra %}{{ parent() }}{% endblock %}
            </ul>
        </div>
    {% endblock %}

The order of the modules can be set by ``install_before`` / ``install_after`` of ``kamod.ini``
(see :doc:`kamod_ini`), but these fields change the order of all classes and templates the modules
override together, not of one template.

Template Code Set in a ``view/*/before`` Event
----------------------------------------------

Ka Extensions library does not support replacing Twig template code via the ``view/*/before`` event
(the ``$code`` argument). Such changes may be ignored, and the standard template (including OCMOD,
kamod and theme editor changes) is used instead. This is a bad practice anyway, because it prevents
Twig cache deployment. If you need to modify the HTML via events (we strictly discourage using events),
use the ``view/*/after`` event.

The ``t()`` Function
--------------------

``t('Text')`` returns a translated language variable. It is defined by Ka Extensions, so a template
that uses it fails with a fatal error when Ka Extensions is not installed.

Use ``t()`` only in templates of your module. To add content to a standard template, include your
template conditionally:

.. code-block:: twig

    {% if is_ka_multivendor_installed %}
        {% include 'default/template/extension/ka_extensions/ka_multivendor/account.twig' %}
    {% endif %}

where ``is_ka_multivendor_installed`` is set by your controller, e.g. with
``\KaGlobal::isKaInstalled('ka_multivendor')``.
