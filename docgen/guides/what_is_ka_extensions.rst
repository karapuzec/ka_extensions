What is "Ka Extensions"
======================

"Ka Extensions" is a shared library for Opencart-based stores that provides a collection of helpful functions and classes
used across multiple modules.

It is distributed as a separate module so that updates and bug fixes can be applied centrally,
without requiring updates to each individual module.

KaMod Engine
------------

The **KaMod Engine** is an alternative to the standard OpenCart modification systems such as OCMOD and VQMod.

Its primary goal is to allow developers to build robust modules without modifying core OpenCart files.
Instead of patching files (as done by OCMOD/VQMod/Events), KaMod introduces an inheritance-based approach.

Key features:

- Define class inheritance to extend or override existing functionality
- Override class methods using standard object-oriented principles
- Extend or replace templates in a structured and maintainable way

This approach results in cleaner, more maintainable, and more predictable module behavior.


KaPatch
-------

**KaPatch** is a component of the KaMod system that provides patching capabilities similar to OCMOD and VQMod.

It allows developers to modify existing files using simple rules:

- Direct text replacement
- Regular expression-based replacement

KaPatch is designed to be lightweight and easy to use for small, targeted modifications.


Database Utilities
------------------

The library includes several utilities for working with the database more efficiently.

Indirect database tools:

- **QueryBuilder** - simplifies building SQL queries
- **ADBTable** - provides abstraction for working with database tables

Direct database helpers:

- **ka_insert**
- **ka_update**

These helper functions accept array-based input, making queries easier to construct,
modify, and maintain.


Controller Utilities
--------------------

Provides helper functionality for modifying controller behavior.

Example features:

- Temporarily disabling output
- Adjusting execution flow of parent controllers

These tools are useful when extending or customizing existing controller logic.


UI Components and Forms
-----------------------

Includes a set of reusable classes and templates for quickly building:

- Standard pages
- Entity forms

These components help reduce development time and ensure consistency across modules.


Extended Entity Support
-----------------------

Adds extended functionality for working with core OpenCart entities:

- Languages
- Stores
- Users
- Other system entities

This simplifies interaction and manipulation of these entities in custom modules.


Mail Functionality
------------------

Provides utilities for sending emails in a structured and reusable way.

Includes:

- Simplified mail submission
- Integration-friendly design


Mail Logging
------------

Extends standard mail functionality with detailed logging capabilities.

This allows developers to:

- Track email delivery attempts
- Debug mail-related issues
- Maintain audit logs of outgoing messages


Performance Improvements and Fixes
---------------------------------

Includes various optimizations and fixes for standard OpenCart behavior.

These improvements aim to:

- Increase performance
- Resolve common issues
- Enhance overall system stability


