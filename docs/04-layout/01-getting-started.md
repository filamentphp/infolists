---
title: Getting started
---

## Overview

Filament infolists are not limited to just displaying entries. You can also use "layout components" to organize them into an infinitely nestable structure.

Layout component classes can be found in the `Filament\Infolists\Components` namespace. They reside within the schema of your infolist, alongside any [entries](entries).

Components may be created using the static `make()` method. Usually, you will then define the child component `schema()` to display inside:

```php
use Filament\Infolists\Components\Grid;

Grid::make(2)
    ->schema([
        // ...
    ])
```

## Available layout components

Filament ships with some layout components, suitable for arranging your form fields depending on your needs:

- [Grid](grid)
- [Fieldset](fieldset)
- [Tabs](tabs)
- [Section](section)
- [Card](card)
- [Split](split)

You may also [create your own custom layout components](custom) to organize fields however you wish.

## Setting an ID

You may define an ID for the component using the `id()` method:

```php
use Filament\Infolists\Components\Card;

Card::make()
    ->id('main-card')
```

## Adding extra HTML attributes

You can pass extra HTML attributes to the component, which will be merged onto the outer DOM element. Pass an array of attributes to the `extraAttributes()` method, where the key is the attribute name and the value is the attribute value:

```php
use Filament\Infolists\Components\Group;

Card::make()
    ->extraAttributes(['class' => 'custom-card-style'])
```

Classes will be merged with the default classes, and any other attributes will override the default attributes.

## Global settings

If you wish to change the default behaviour of a component globally, then you can call the static `configureUsing()` method inside a service provider's `boot()` method, to which you pass a Closure to modify the component using. For example, if you wish to make all card components have [2 columns](grid) by default, you can do it like so:

```php
use Filament\Infolists\Components\Card;

Card::configureUsing(function (Card $card): void {
    $card
        ->columns(2);
});
```

Of course, you are still able to overwrite this on each field individually:

```php
use Filament\Infolists\Components\Card;

Card::make()
    ->columns(1)
```
