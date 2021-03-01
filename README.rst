TYPO3 Exception Page Creation
=============================

Besides editing existing
`TYPO3 Exception Pages <https://docs.typo3.org/typo3cms/exceptions/master/en-us/Index.html>`_
on docs.typo3.org, the user should also be able to create a new exception page –
without being distracted from the usual workflow.

Therefore, the web server simulates a default exception page with the familiar
"Edit on GitHub" button when it cannot find the requested exception page.
When the user clicks the button to add their experience with this exception,
the web server creates the new exception page on-the-fly before redirecting
the user to the usual GitHub edit form.

This is the same exception page creation screen of the former wiki.typo3.org
and the future docs.typo3.org instance:

.. table::
   :widths: auto

   ================================================   ================================================
   Wiki (old)                                         Docs (new)
   ================================================   ================================================
   .. image:: docs/exception_page_creation_wiki.png   .. image:: docs/exception_page_creation_docs.png
   ================================================   ================================================

Installation
------------

1. Install `Docker <https://docs.docker.com/get-docker/>`_ and `Docker-Compose <https://docs.docker.com/compose/install/>`_.
2. Build the environment by

   .. code-block:: bash

      # Linux
      make build

      # MacOS and Windows
      docker-compose build --force-rm
      docker-compose -f admin.yml run --rm composer-install

3. Copy ``app/config.inc.php`` to ``app/config.php`` and insert
   the GitHub username and token of the user who has write access to the
   `TYPO3 Exception Pages repository <https://github.com/TYPO3-Documentation/TYPO3CMS-Exceptions>`_
   via GitHub API. See the
   `GitHub Docs <https://docs.github.com/en/github/authenticating-to-github/creating-a-personal-access-token>`_
   for further details on how to create an access token.

Maintenance
-----------

.. _fetch-exception-codes:

Fetch Exception Codes
~~~~~~~~~~~~~~~~~~~~~

1. Get the list of available exception codes for each TYPO3 release by running

   .. code-block:: bash

      # Linux
      make fetch

      # MacOS and Windows
      docker-compose -f admin.yml run --rm fetch-exception-code-files

   It pulls the current TYPO3 Git repository, checks out each TYPO3 release and
   creates an according exception code file in folder
   ``app/packages/exception-pages/res/exceptions`` - if it does not exist
   already.

2. Merge all exception code files into one final aggregation file by

   .. code-block:: bash

      # Linux
      make merge

      # MacOS and Windows
      docker-compose -f admin.yml run --rm merge-exception-code-files

   It traverses all JSON files and produces the final PHP file
   ``app/packages/exception-pages/res/exceptions/exceptions.php`` which serves
   as the list of exception codes available for page creation.

– or, instead of (1) and (2) –

3. Combine both commands of fetching and merging new exception codes by

   .. code-block:: bash

      # Linux
      make update

      # MacOS and Windows
      docker-compose -f admin.yml run --rm update-exception-code-files

Repeat these steps always when a new TYPO3 version has been released.

.. _refresh-exception-page-template:

Refresh Exception Page templates
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

The default exception page template is fetched from an existing exception page
and exception code specific parts are replaced with placeholders. The lifetime
of the template is one day, so changes in the general docs.typo3.org template
are reflected in the default exception page in a timely manner.
To update the template manually

1. Temporarily decrease the template lifetime to 0 seconds by setting

   .. code-block:: php

      ..
      'template' => [
         'lifetime' => 0
      ]

   in the configuration file ``app/config.php``.

2. Refresh the template files ``app/packages/exception-pages/res/pageDefault.html``
   and ``app/packages/exception-pages/res/pageError.html`` by
   following the steps (1)-(3) of `Manual testing <manual-testing_>`_.
3. Revert the template lifetime to one day by setting

   .. code-block:: php

      ..
      'template' => [
         'lifetime' => 24 * 3600
      ]

   in the configuration file.

.. _manual-testing:

Manual testing
--------------

1. Bring up the webserver by

   .. code-block:: bash

      # Linux
      make start

      # MacOS and Windows
      docker-compose up

2. Chose an arbitrary exception number from the array of exception code file
   ``app/packages/exception-pages/res/exceptions/exceptions.php``.
   Make sure, that the corresponding TYPO3 Exception Page does not exist yet at

   .. code-block::

      https://github.com/TYPO3-Documentation/TYPO3CMS-Exceptions/Documentation/Exceptions/{exceptionnumber}.rst

3. Open your browser at

   .. code-block::

      http://localhost:8080/exceptions/{exceptionnumber}.html

   and confirm that it looks like an ordinary TYPO3 Exception Page.

4. Click the "Edit on GitHub" button and confirm that you get redirected to
   the usual GitHub edit form for file

   .. code-block::

      https://github.com/TYPO3-Documentation/TYPO3CMS-Exceptions/Documentation/Exceptions/{exceptionnumber}.rst

5. Cancel the editing and confirm that there is a new commit in the repository
   history with commit message

   .. code-block::

      [TASK] Create page for exception {exceptionnumber}

6. Confirm that repeating clicks on the "Edit on GitHub" button do not end in
   errors.
7. Bring down the webserver by

   .. code-block:: bash

      # Linux
      make stop

      # MacOS and Windows
      docker-compose down

Deployment
----------

1. The essence for the production environment is

   *  the PHP application in folder `app <app>`_
   *  the Nginx configuration in file `default.conf <nginx/files/etc/nginx/conf.d/default.conf>`_

2. Each push to the remote branch ``master`` triggers a deployment to the production
   server. Thus make sure that you

   *  fetched, merged and committed the latest exception codes as written in
      `Fetch Exception Codes <fetch-exception-codes_>`_
   *  fetched, merged and committed the latest page templates as written
      in `Refresh Exception Page template <refresh-exception-page-template_>`_

Uninstallation
--------------

Remove the environment from your operating system by

.. code-block:: bash

   # Linux
   make clean

   # MacOS and Windows
   docker-compose down --rmi all --volumes
