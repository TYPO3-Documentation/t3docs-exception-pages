TYPO3 Exception Page Creation
=============================

Besides editing existing
`TYPO3 Exception Pages <https://docs.typo3.org/typo3cms/exceptions/master/en-us/Index.html>`_
on docs.typo3.org, the user should also be able to create a new exception page –
without being distracted from the usual workflow.

Therefore, the web server simulates a dummy exception page with the familiar
"Edit on GitHub" button when it cannot find the requested exception page.
When the user clicks the button to add their experience with this exception,
the web server creates the new exception page on-the-fly before redirecting
the user to the usual GitHub edit form.

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

3. Fetch and merge all exception codes of the currently actively supported
   TYPO3 LTS (>= 9 LTS) by

   .. code-block:: bash

      # Linux
      make update

      # MacOS and Windows
      docker-compose -f admin.yml run --rm update-exception-code-files

   It makes use of the aggregation file of exception codes for the unsupported
   TYPO3 releases (< 9 LTS)
   ``app/packages/exception-pages/res/exceptions/aggregation-3.6-8.7.json``,
   fetches all exception code files of the actively supported TYPO3 LTS -
   which do not exist yet - and merges the final aggregation file again.

Repeat these steps always when a new TYPO3 version has been released.

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

   .. image:: docs/typo3_exception_page_simulation.png

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

The essence for the production environment is

*  the PHP application in folder `app <app>`_
*  the Nginx configuration in file `default.conf <nginx/files/etc/nginx/conf.d/default.conf>`_

Uninstallation
--------------

Remove the environment from your operating system by

.. code-block:: bash

   # Linux
   make clean

   # MacOS and Windows
   docker-compose down --rmi all --volumes
