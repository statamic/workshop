# Workshop ![Statamic 2.0](https://img.shields.io/badge/statamic-2.0-blue.svg?style=flat-square)
> The do-it-all-for-you front-end content creation and updatification addon.

## Overview

Workshop gives you the ability to create and edit Entries, Pages, and Globals
on the _front-end_ of your Statamic 2 website though a handful of nice and
easy-to-use Tags.

## Installation

- Unzip the package into `/site/addons/Workshop`.
- Yeah, that's it.

## Usage

All Workshop Tags follow the same pattern: `{{ workshop:noun:verb }}`.
So for example, if you want to create an entry, use `workshop:entry:create`.
Pretty straight forward.

When editing, if you don't specify a `url` or `id` to edit, it will assume the current URL.

## Tags

- [workshop:entry:create](#entry-create)
- [workshop:entry:edit](#entry-edit)
- [workshop:page:create](#page-create)
- [workshop:page:edit](#page-edit)
- [workshop:global:edit](#page-edit)

### Entry:Create {#entry-create}

When creating an entry, the only required parameter is the name of the collection.

```
{{ workshop:entry:create }}
    <input type="text" name="title" value="{{ old:title }}">
    <input type="submit">
{{ /workshop:entry:create }}

```

### Entry:Edit {#entry-edit}

You can use `id` or `url` to pick which entry to edit. If unset, will assume current URL.
```
{{ workshop:entry:edit id="123abc" }}
    <input type="text" name="title" value="{{ title }}">
    <input type="submit">
{{ /workshop:entry:edit }}
```

### Page:Create {#page-create}

If you don't set a `parent` param/value, will create a top-level Page.
```
{{ workshop:page:create parent="about" }}
    <input type="text" name="title" value="{{ old:title }}">
    <input type="submit">
{{ /workshop:page:create }}
```

### Page:Edit {#page-edit}
You can use `id` or `url` to pick which page to edit. If unset, will assume current URL.
```
{{ workshop:page:edit id="987xyz" }}
    <input type="text" name="title" value="{{ title }}">
    <input type="submit">
{{ /workshop:page:create }}
```

### Global:Edit {#global-edit}

Give the `set` name to pick which Global set to edit. If unset, will assume the base default `globals`.
```
{{ workshop:global:edit set="company" }}
    <input type="text" name="site_name" value="{{ site_name }}">
    <input type="submit">
{{ /workshop:global:edit }}
```

## Parameters

All tags respect the same parameters, given the appropriate context.
Each parameter may instead be set as a form input field, but
the parameter will always override. For safety.

- `id` The page or entry's ID to edit. Will default to current URL if not set.
- `collection` The handle/slug of collection. **Required** if creating/editing entries.
- `fieldset` Will fall back to the appropriate default fieldset if not set.
- `url` When editing, you can pass the URL of the file you want to edit.
- `date` If working with a date ordered Collection. Defaults to `today`.
- `order` If working with pages or ordered Collections, sets the order.
- `set` If working with Globals, the Globalset name.
- `parent` Sets the parent page, when creating Pages.
- `published` Sets the published state. Defaults to `true`.
- `slug` Sets the slug of the entry or page.
- `slugify` Assigns a field to be automatically slugified to make the slug.
- `attr` Set any HTML attributes on the `<form>` tag. You can set multiple by pipe delimiting them. eg. `attr="class:pretty-form|id:contact"``
- `redirect` The location your user will be taken after a successful form submission. If left blank, the user will stay on the same page.

## Settings

All settings can be managed in the Addons > Workshop > Settings screen, or by setting them in
`site/addons/workshop.yaml`. The defaults all lean towards security over flexibility.

- `enforce_auth` Only allow Workshop features to work when logged in. Defaults to `true`.
- `whitelist`: Only save data in fields that match those in your fieldset. Defaults to `true`.
