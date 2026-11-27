Installation
============

You can install ArrayKit via Composer.

.. code-block:: bash

    composer require infocyph/arraykit

Requirements
------------

ArrayKit has the following requirements:

* **PHP 8.4+** (CI matrix: PHP 8.4, 8.5 and 8.6)
* **ext-filter**, **ext-hash** (strict value-set acceleration) and **ext-tokenizer**

For locale-aware sorting outside the C/POSIX collation locale, install the optional
``ext-intl`` extension.

Autoload is PSR-4 based and loads namespaced helper functions from
``Infocyph\ArrayKit\*`` by default.

Optional global helpers
-----------------------

If you want global helper functions (``array_get()``, ``array_set()``,
``collect()``, ``chain()``), include ``src/functions.php`` manually:

.. code-block:: php

    <?php
    require_once __DIR__ . '/vendor/infocyph/arraykit/src/functions.php';
