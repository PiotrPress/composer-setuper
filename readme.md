# Composer Setuper

This Composer plugin allows realizing full setup process (also interactively) of your project according to defined steps.

## Usage example

Take a look at `extra.setup` section in `composer.json` file of the [WordPress Plugin Template](https://github.com/PiotrPress/wordpress-plugin-template/blob/master/composer.json) project.

## Installation

Setuper can be simply required like a regular dependency package or can be installed like a global plugin.

### Project dependency

1. Add Setuper as project dependency:

```shell
$ composer require piotrpress/composer-setuper
```

2. Allow plugin execution:

```shell
$ composer config allow-plugins.piotrpress/composer-setuper true
```

### Global dependency

1. Add command as a global dependency:

```shell
$ composer global require piotrpress/composer-setuper
```

2. Allow plugin execution:

```shell
$ composer config -g allow-plugins.piotrpress/composer-setuper true
```

## Defining setup

### Actions

Actions are setup steps that are firing during the Composer execution process.

You can define actions in `composer.json` [extra](https://getcomposer.org/doc/04-schema.md#extra) property as an array of objects in `setup` property e.g.:

```json
{
  "extra": {
    "setup": [
      {
        "action": "write",
        "message": "Hello world!"
      }
    ]
  }
}
```

Setup can also be a PHP callback (defined as a static method) e.g.:

```json
{
  "extra": {
    "setup": "MyVendor\\MyClass::setup"
  }
}
```

```php
namespace MyVendor;

class MyClass {
    static public function setup() {
        return [
            [
                'action' => 'write',
                'message' => 'Hello world!'
            ]
        ];
    }
}
```

Single action can also be a PHP callback (defined as a static method) e.g.:

```json
{
  "extra": {
    "setup": [
      {
        "action": "write",
        "message": "Hello"
      },
      "MyVendor\\MyClass::writeWorld"
    ]
  }
}
```

```php
namespace MyVendor;

class MyClass {
    static public function writeWorld() {
        return [
            'action' => 'write',
            'message' => 'world!'
        ];
    }
}
```

**NOTE:** PHP classes containing defined callbacks must be autoloadable via Composer's autoload functionality.

### Events

Actions are triggered by [events](https://getcomposer.org/doc/articles/scripts.md#event-names).

```json
{
  "extra": {
    "setup": [
      {
        "action": "write",
        "message": "Hello world!",
        "event": "post-update-cmd"
      }
    ]
  }
}
```

If you don't specify the event for the action, then it'll be assigned to `setup` script, which you can use in `composer.json` [scripts](https://getcomposer.org/doc/articles/scripts.md#defining-scripts) property e.g.:

```json
{
  "scripts": {
    "post-update-cmd": "@setup"
  }
}
```

You can also run it manually from command line:

```shell
$ composer run setup
```

### Priorities

Actions calls within an event are sorted by [priority](https://getcomposer.org/doc/articles/plugins.md#event-handler).
By default, the priority of an action is set to `0`.
Higher value represent higher priorities.

```json
{
  "extra": {
    "setup": [
      {
        "action": "write",
        "message": "world!"
      },
      {
        "action": "write",
        "message": "Hello",
        "priority": 1
      }
    ]
  }
}
```

## Input / Output actions

The most powerfull and common usage feature of Setuper is interactively communicating with the user due setup process. 
Setuper can taking users inserted values using `insert`, `secret`, `select` or `confirm` actions and sets the variables for future reference in other (e.g. filesystem manipulation) actions. 

### Set 

Sets value to variable for future reference.

```json
{
  "action": "set",
  "variable": "greeting",
  "value": "Hello world!"
}
```

#### Parameters

- `variable` - `required`|`string` - name of the variable following the PHP naming [rules](https://www.php.net/manual/en/language.variables.basics.php).
- `value` - `required`|`mixed` - value of the variable.

### Insert

Sets value to variable passed by the user.

```json
{
  "action": "insert",
  "variable": "greeting",
  "message": "Insert your greeting:"
}
```

#### Parameters

- `variable` - `required`|`string` - name of the variable following the PHP naming [rules](https://www.php.net/manual/en/language.variables.basics.php).
- `message` - `required`|`string` - outputs message for the user to the console.
- `required` - `optional`|`boolean` - whether the value is required or not.
- `validator` - `optional`|`collable`|`collable[]` - the PHP callback (defined as a static method)
- `default` - `optional`|`string` - the default value if none is given by the user.

**NOTE:** `insert` action uses [ask()](https://symfony.com/doc/current/components/console/helpers/questionhelper.html#validating-the-answer) function from Symfony Console component, so for detailed info (e.g. how validating the value) check out the documentation.

### Secret

Sets value to variable passed by the user.

```json
{
  "action": "secret",
  "variable": "password",
  "message": "Insert your password:"
}
```

#### Parameters

- `variable` - `required`|`string` - name of the variable following the PHP naming [rules](https://www.php.net/manual/en/language.variables.basics.php).
- `message` - `required`|`string` - outputs message for the user to the console.


### Select

Sets value to variable passed by the user.

```json
{
  "action": "select",
  "variable": "color",
  "message": "Select your favourite color:",
  "choices": [ "red", "green", "blue" ]
}
```

#### Parameters

- `variable` - `required`|`string` - name of the variable following the PHP naming [rules](https://www.php.net/manual/en/language.variables.basics.php).
- `message` - `required`|`string` - outputs message for the user to the console.
- `choices` - `required`|`string[]` - an array of choices.
- `default` - `optional`|`string` - the default value if none is given by the user.
- `error` - `optional`|`string` - the error message if the value is not in the choices array.
- `multiple` - `optional`|`boolean` - whether the user can select multiple choices or not.

### Confirm

Sets value to variable passed by the user.

```json
{
  "action": "confirm",
  "variable": "help",
  "message": "Can I help you?"
}
```

#### Parameters

- `variable` - `required`|`string` - name of the variable following the PHP naming [rules](https://www.php.net/manual/en/language.variables.basics.php).
- `message` - `required`|`string` - outputs message for the user to the console.
- `default` - `optional`|`boolean` - the default value if none is given by the user.

### Write

Outputs single or multiple lines message to the console.

```json
{
  "action": "write",
  "message": "Hello world!"
}
```

#### Parameters

- `message` - `required`|`string`|`string[]` - the message as a single string or an array of lines.
- `verbose` - `optional`|`string` - one of the verbosity level: `quiet`, `normal`, `verbose`, `very_verbose`, `debug`

**NOTE:** `write` action uses [writeln()](https://symfony.com/doc/current/console/coloring.html) function from Symfony Console component, so for detailed info (e.g. how to color and style the message) check out the documentation.

## Filesystem actions

### Directory

Creates a directory.

```json
{
  "action": "directory",
  "path": "src"
}
```

#### Parameters

- `path` - `required`|`string`|`string[]` - path to the directory. An array may be used to designate multiple directories.

### Symlink

Creates a symbolic link.

```json
{
  "action": "symlink",
  "source": "src",
  "target": "inc"
}
```

#### Parameters

- `source` - `required`|`string`|`string[]` - path to the source directory. An array may be used to designate multiple directories.
- `target` - `required`|`string`|`string[]` - path to the target directory. An array may be used to designate multiple directories.

### Rename

Renames a file or directory.

```json
{
  "action": "rename",
  "source": "src/hello.php",
  "target": "src/hi.php"
}
```

#### Parameters

- `source` - `required`|`string`|`string[]` - path to the source file or directory. An array may be used to designate multiple files or directories.
- `target` - `required`|`string`|`string[]` - path to the target file or directory. An array may be used to designate multiple files or directories.

### Copy

Copies a file or directory.

```json
{
  "action": "copy",
  "source": "src/hello.php",
  "target": "inc/hello.php"
}
```

#### Parameters

- `source` - `required`|`string`|`string[]` - path to the source file or directory. An array may be used to designate multiple files or directories.
- `target` - `required`|`string`|`string[]` - path to the target file or directory. An array may be used to designate multiple files or directories.

### Move

Moves a file or directory.

```json
{
  "action": "move",
  "source": "src/hello.php",
  "target": "inc/hello.php"
}
```

#### Parameters

- `source` - `required`|`string`|`string[]` - path to the source file or directory. An array may be used to designate multiple files or directories.
- `target` - `required`|`string`|`string[]` - path to the target file or directory. An array may be used to designate multiple files or directories.

### Remove

Removes a file or directory.

```json
{
  "action": "remove",
  "path": "src/hello.php"
}
```

#### Parameters

- `path` - `required`|`string`|`string[]` - path to the file or directory. An array may be used to designate multiple files or directories.

### Owner

Changes the owner of a file or directory.

```json
{
  "action": "owner",
  "path": "src/hello.php",
  "owner": "www-data"
}
```

#### Parameters

- `path` - `required`|`string`|`string[]` - path to the file or directory. An array may be used to designate multiple files or directories.
- `owner` - `required`|`string`|`string[]` - the owner value. An array may be used to designate multiple owners.

### Group

Changes the group of a file or directory.

```json
{
  "action": "group",
  "path": "src/hello.php",
  "group": "www-data"
}
```

#### Parameters

- `path` - `required`|`string`|`string[]` - path to the file or directory. An array may be used to designate multiple files or directories.
- `group` - `required`|`string`|`string[]` - the group value. An array may be used to designate multiple groups.

### Mode

Changes the mode of a file or directory.

```json
{
  "action": "mode",
  "path": "src/hello.php",
  "mode": "0755"
}
```

#### Parameters

- `path` - `required`|`string`|`string[]` - path to the file or directory. An array may be used to designate multiple files or directories.
- `mode` - `required`|`string`|`string[]` - the mode value. An array may be used to designate multiple modes.

## File content actions

### Dump

Dump the content to the file.

```json
{
  "action": "dump",
  "content": "<?= 'Hello world!';",
  "file": "src/hello.php"
}
```

#### Parameters

- `content` - `required`|`string`|`string[]` - the content being written. An array may be used to designate multiple contents.
- `file` - `required`|`string`|`string[]` - path to file where the content will be written. An array may be used to designate multiple files.

### Append

Append the content to the file.

```json
{
  "action": "append",
  "content": "<?= 'Hello world!';",
  "file": "src/hello.php"
}
```

#### Parameters

- `content` - `required`|`string`|`string[]` - the content being appended. An array may be used to designate multiple contents.
- `file` - `required`|`string`|`string[]` - path to file where the content will be appended. An array may be used to designate multiple files.


### Replace

Replace all occurrences of the pattern with the replacement string in file content.

```json
{
  "action": "replace",
  "pattern": "/Hello world!/i",
  "replace": "Hi world!",
  "file": "src/**/*.php"
}
```

#### Parameters

- `pattern` - `required`|`string`|`string[]` - the pattern being searched for. An array may be used to designate multiple patterns.
- `replace` - `required`|`string`|`string[]` - the replacement value that replaces found search values. An array may be used to designate multiple replacements.
- `file` - `required`|`string`|`string[]` - path to file where the replacement will be made. An array may be used to designate multiple files. You can use `*` to match any string, or `**` to match any string that spans directories.

**NOTE:** `Replace` action uses [preg_replace()](https://www.php.net/manual/en/function.preg-replace.php) and [Symfony Finder Component](https://symfony.com/doc/current/components/finder.html), so for detailed info check out the documentation. 

## Requirements

- PHP > = `7.4`
- Composer >= `2.0`

## License

[MIT](license.txt)