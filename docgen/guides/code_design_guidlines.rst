Code Design Guidelines
======================

1. Avoid Using ``final`` for Classes
-----------------------------------

The ``final`` keyword prevents other developers from extending or modifying a class.
If you want your module to remain flexible and extensible, avoid using the
``final class`` construct.


2. Avoid Using ``private``
-------------------------

The same limitation applies to the ``private`` keyword. It restricts access too strictly
and makes extension difficult.

Avoid using ``private`` where possible. Use ``protected`` instead if you need to hide
internal implementation details while still allowing inheritance.


3. Use QB (QueryBuilder) for Data Selection
------------------------------------------

Direct SQL queries in modules may seem clear and simple, but they are difficult to extend.
If you use the QB (QueryBuilder) class, your queries can be extended more easily by other modules.

A recommended pattern is shown below.

For example, suppose you need to return a list of customers selected by your module.
You can create a model class with the following methods:

::

    public function getCustomers($data) {
        $qb = $this->getCustomersQB($data);
        $rows = $qb->query()->rows;
        return $rows;
    }

    protected function getCustomersQB($data) : QB {
        $qb = new \extension\ka_extensions\QB();
        ...
        return $qb;
    }

With this approach, other developers can modify the behavior of your module
by altering the QB object returned by the ``getCustomersQB`` method.


4. Avoid Direct File Inclusion
-----------------------------

Do not include files directly using ``require`` or ``include`` directives.

File paths may change, which can cause these directives to fail. Additionally,
such files may be skipped by the kamod engine.


5. Always Call the Parent Method
--------------------------------

When overriding a method, you should call the parent method unless you are absolutely sure
that the entire behavior must be replaced.

Keep in mind that the same method may also be modified by other modules. Skipping the parent
call can lead to conflicts or broken functionality.


6. Apply Patches with Minimal Changes
------------------------------------

In some cases, patching existing code is unavoidable. However, modifications to original files
should be kept to a minimum.

Try to move all custom logic into separate functions within your module, and insert only minimal
calls to those functions into the original code.

For Twig templates, prefer using ``include`` directives to reference your custom template files
instead of modifying existing templates directly.

These practices help minimize conflicts with other modules and improve maintainability.