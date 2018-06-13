## Installing

- Unzip and place the `Workshop` directory into `/site/addons`.
- Yeah, that's it.

## Usage

All Workshop Tags follow the same pattern: `{{ workshop:noun:verb }}`.
So for example, if you want to create an entry, use `workshop:entry:create`.
Pretty straight forward.

When editing, if you don't specify a `url` or `id` to edit, it will assume the current URL.

## Tags

- [workshop:entry:create](#entrycreate)
- [workshop:entry:edit](#entryedit)
- [workshop:entry:delete](#entrydelete)
- [workshop:page:create](#pagecreate)
- [workshop:page:edit](#pageedit)
- [workshop:page:delete](#pagedelete)
- [workshop:global:edit](#pageedit)

### Entry:Create

When creating an entry, the only required parameter is the name of the collection.

```
{{ workshop:entry:create collection="words" }}
    <input type="text" name="title" value="{{ old:title }}">
    <input type="submit">
{{ /workshop:entry:create }}
```

### Entry:Edit

You can use `id` or `url` to pick which entry to edit. If unset, will assume current URL.
```
{{ workshop:entry:edit id="123abc" }}
    <input type="text" name="title" value="{{ title }}">
    <input type="submit">
{{ /workshop:entry:edit }}
```

### Entry:Delete

You can use `id` or `url` to pick which entry to delete. If unset, will assume current URL.
```
{{ workshop:entry:delete id="123abc" }}
    <input type="submit">
{{ /workshop:entry:delete }}
```

### Page:Create

If you don't set a `parent` param/value, will create a top-level Page.
```
{{ workshop:page:create parent="about" }}
    <input type="text" name="title" value="{{ old:title }}">
    <input type="submit">
{{ /workshop:page:create }}
```

### Page:Edit
You can use `id` or `url` to pick which page to edit. If unset, will assume current URL.
```
{{ workshop:page:edit id="987xyz" }}
    <input type="text" name="title" value="{{ title }}">
    <input type="submit">
{{ /workshop:page:create }}
```

### Page:Delete

You can use `id` or `url` to pick which page to delete. If unset, will assume current URL.
```
{{ workshop:page:delete id="123abc" }}
    <input type="submit">
{{ /workshop:page:delete }}
```

### Global:Edit

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

`id`

The page or entry's ID to edit. Will default to current URL if not set.

`collection`

The handle/slug of collection. **Required** if creating/editing entries.

`fieldset`

Will fall back to the appropriate default fieldset if not set.

`url`

When editing, you can pass the URL of the file you want to edit.

`date`

If working with a date ordered Collection. Defaults to `today`.

`order`

If working with pages or ordered Collections, sets the order.

`set`

If working with Globals, the Globalset name.

`parent`

Sets the parent page, when creating Pages.

`published`
Sets the published state. Defaults to `true`.

`slug`

Sets the slug of the entry or page.

`slugify`

Assigns a field to be automatically slugified to make the slug.

`attr`

Set any HTML attributes on the `<form>` tag. You can set multiple by pipe delimiting them. eg.  `attr="class:pretty-form|id:contact"`

`redirect`

The location your user will be taken after a successful form submission. If left blank, the user will stay on the same page.

`files`

Whether the form should accept file uploads. (It adds `enctype="multipart/form-data"` to your form tag) See [file uploads](#file-uploads) for more details.

## Variables

`old`

An array of previous form input data, used for repopulating the form after a submission with errors, without losing your input. E.g. `{{ old:title }}`, `{{ old:content }}`.

`success`

This will be `true` if the form was submitted successfully.

`errors`

A tag pair of error messages return by validation. Example:
```
{{ errors }}
    <li>{{ value }}</li>
{{ /errors }}
```

## Settings

All settings can be managed in the Addons > Workshop > Settings screen, or by setting them in `site/addons/workshop.yaml`. The defaults all lean towards security over flexibility.

`enforce_auth`

Only allow Workshop features to work when logged in. Defaults to `true`.

`whitelist`

Only save data in fields that match those in your fieldset. Defaults to `true`.

## File Uploads

First up, add `files="true"` to your form tag. (This will add `enctype="multipart/form-data"` to the generated `<form>` tag. That’s always so difficult to remember.)

```
{{ workshop:entry:edit files="true" }}
    ...
{{ /workshop:entry:edit }}
```

Then add a `file` input for your corresponding asset field.

``` yaml
fields:
  my_image_field:
    type: assets
    max_files: 1
```

```
<input type="file" name="my_image_field" />
```

### Multiple files

For asset fields with a `max_files` setting greater than `1` (or not set at all), you can indicate an array by adding square brackets to the input `name`.

```
Image One: <input type="file" name="my_image_field[]" />
Image Two: <input type="file" name="my_image_field[]" />
```

## Troubleshooting

**Submitting checkboxes or other array-type values are treated as strings**

Be sure that the input's `name` attribute contains a pair of brackets.

```
<input type="checkbox" name="choices[]" value="first">
<input type="checkbox" name="choices[]" value="second">
```

If you leave off the brackets, the form will only submit the last value.
