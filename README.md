# Ka Extensions Framework for OpenCart 3

Ka Extensions is a powerful development framework designed to accelerate the creation of robust OpenCart extensions. It introduces an architectural layer to OpenCart, providing developers with advanced tools for class inheritance, template overriding, and rapid UI development.

## Key Features
### 🚀 KaMod Engine (Core Feature)
KaMod is an alternative to traditional OCMod/VQMod patching. Instead of injecting code into files, KaMod uses a class-based inheritance system.

**Isolated Code**: Every modification is stored in its own directory, eliminating conflicts between multiple modules.

**Native Inheritance**: It generates a transparent parent-child class cache, allowing you to extend core and custom classes naturally.

**Twig Inheritance**: Extends Twig functionality by using extend/block methods for template modifications.

**Compatibility**: Works seamlessly alongside OpenCart Events and VQMod.

**Developer Friendly**: Easy to debug. The cache is stored in storage/cache.kamod, where you can browse the generated class hierarchy.

## 🛠 Rapid Development Tools
**Entity Management**: High-level classes to simplify the creation of standard pages, such as entity lists (CRUD) and management interfaces.

**SQL Query Builder**: A flexible and secure builder to construct complex SQL queries without manual string concatenation.

**Database Helpers**: Optimized functions for efficient INSERT and UPDATE operations.

## 🎨 Template & Performance
**Twig Enhancements**: Additional useful functions and filters for Twig templates to simplify front-end logic.

**Performance Boost**: Includes several core-level optimizations to speed up the OpenCart engine execution.

## How It Works
### The KaMod Philosophy
Unlike OCMod, which searches and replaces strings, KaMod builds a virtual class tree. When you want to modify a class, you create a child class in the kamod directory. The framework then:
Detects your modification.
Generates a cached version of the class hierarchy.
Hooks into the OpenCart autoloader to serve the modified version.

## Installation & Usage
KaMod is activated at the very beginning of the OpenCart bootup sequence, ensuring that almost any standard file can be adjusted.
Cache Location: storage/cache.kamod/

## Why Ka Extensions?
**Maintainability**: Modifications are simple, natural, and don't break when the original file changes slightly.

**Extensibility**: Extensions built with KaMod are inherently easier for other developers to modify or extend.

**Zero Conflict**: No more "search string not found" errors common in OCMod.

## License
This framework is free to use in your own projects. If you are making changes to karapuz team extensions, using KaMod is the recommended way to ensure compatibility.
