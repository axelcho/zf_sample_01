zf_sample_01
============

universal search project

This is a stub sample of a zend framework 2 project.

A client wanted to add a "universal search" function, where users can search the website by entering keywords.
The keywords can be a name, job position, or show title. 


This task consist of two parts:

1) Every page should have "Universal Search" boxes, which is autocomplete

2) If a user choose to use non-autocomplete term, the page should go to a "universal search" page, where user can do some more operation. 


1) can be done by adding an ajax call. The call is processed by the file
"UniversalSearch.php" shown in this file.

As the autocomplete should call values from 3 different database tables, the standard TableGateway method of zend framework is extended in 3 corresponding files.
They are located in "Table" folder in this sample.

Table/Accounts.php (names)
Table/Positions.php (job positions)
Table/Titles.php (show titles)


2) needs a little more elaboration. As it requires a new page, it requires additions to a control file and the routes.
This sample does not show the whole route of the website, though.

routes.config.php (shown partially here)
SearchController.php

These are the barebone php elements required for this project. But it needs javascript for most client-side actions. 
The phtml template files with javascripts are in /view directory (again, most barebones only). 
