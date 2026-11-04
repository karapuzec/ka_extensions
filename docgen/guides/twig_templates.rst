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

Template Text Set in a ``view/*/before`` Event
----------------------------------------------

In OpenCart a handler of the event ``view/<route>/before`` can replace the text of a template
through the third argument ``$code``: Template\Twig renders ``$code`` instead of the file.

With Ka Extensions the storefront loader looks for the template in its folders first: the kamod
cache (kapatches and child templates of modules), the theme editor copies
(``system/storage/theme.kamod``) and the OCMOD modification files (``system/storage/modification``).
When one of these files exists for the template, it is shown and **the text of** ``$code`` **is
ignored**. ``$code`` is used only for a template without such files. With ka_speedup (one Twig
environment for all templates of the request) ``$code`` is ignored for every template but the first.

Changes made in the Design > Theme Editor do not come in ``$code`` either: Ka Extensions takes them
from ``system/storage/theme.kamod``, the core event ``event/theme`` gets nothing from the database.

To change a template, use a kapatch or a child template of kamod (see :doc:`overriding_core_files`),
they are applied together with the changes of other modules. To change the result of a template,
use the event ``view/<route>/after`` (``$output``).

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
