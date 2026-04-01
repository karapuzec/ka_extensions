Overriding Core Files
=====================

Kamod allows developers to override OpenCart core files by extending them via inheritance.
Once the module is installed, the Kamod cache is rebuilt with a new class structure.

Almost any core file can be safely extended using the Kamod engine, except for a few startup files.

Overriding Controllers
----------------------

To extend a controller file, create a ``controller`` directory inside your module and replicate
the path to the core file.

For example, to override:

::

    admin/controller/catalog/product.php

create the following file inside your module:

::

    admin/controller/extension/mycompany/mymodule/controller/catalog/product.php

The file must use your module namespace and inherit from the original controller class:

.. code-block:: php

    class \extension\mycompany\mymodule\ControllerCatalogProduct extends \ControllerCatalogProduct {

        public function add() {

            // Your custom code goes here...

            // Call the parent method
            parent::add();
        }
    }

This approach gives you full control using OOP principles.

Example: Modifying Controller Output
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

A common use case is modifying the data passed to a template:

.. code-block:: php

    class \extension\mycompany\mymodule\ControllerCatalogProduct extends \ControllerCatalogProduct {

        // Include auxiliary functions
        use \extension\ka_extensions\TraitController;

        public function add() {

            $this->disableRender();
            parent::add();
            $this->enableRender();

            $template = $this->getRenderTemplate();
            $data = $this->getRenderData();

            // Adjust the data passed to the template
            if (empty($data['quantity'])) {
                $data['product_stock'] = $this->language->get('Empty stock');
            }

            $this->response->setOutput($this->load->view($template, $data));
        }
    }

Overriding Models
-----------------

The same approach applies to OpenCart models.

For example, to override the product model in the admin panel, create:

::

    admin/model/extension/mycompany/mymodule/catalog/product.php

Then extend the core model class:

.. code-block:: php

    class \extension\mycompany\mymodule\ModelCatalogProduct extends \ModelCatalogProduct {

        public function addProduct($data) {

            $product_id = parent::addProduct($data);
            $this->updateCustomField($product_id, $data);

            return $product_id;
        }

        public function editProduct($product_id, $data) {

            parent::editProduct($product_id, $data);
            $this->updateCustomField($product_id, $data);
        }
    }

Overriding Template Files
-------------------------

A similar concept applies to template files.

.. note::

    Kamod supports Twig templates only. PHP-based templates are not supported.

To override a template file, create it inside your module directory:

::

    admin/view/template/extension/mycompany/mymodule/template/catalog/product_form.twig

By default, this file completely replaces the original template. However, you can include
the parent template by calling:

.. code-block:: twig

    {{ parent() }}