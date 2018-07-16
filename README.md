# Workshop ![Statamic 2.1.0](https://img.shields.io/badge/statamic-2.1.0-blue.svg?style=flat-square)

Create and edit entries, pages, and globals on the front-end of your site without the control panel.

## Documentation

Read it on the [Statamic Marketplace](https://statamic.com/marketplace/addons/workshop/docs) or contribute to it [here on GitHub](DOCUMENTATION.md).

## Developing

Ensure you have an `.env` file and adjust `STATAMIC_PATH` to the path to your Statamic development site:

```
cp .env.example .env
```

Install npm dependencies:

```
npm install
```

Run the watcher. This will copy files to your development site's `site/addons/Workshop` directory and re-copy them whenever you make a change.

```
npm run watch
```

**Make sure you edit files in this repo and not in your Statamic dev site.**
