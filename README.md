# Workshop

- [On the Marketplace](https://statamic.com/marketplace/addons/workshop)
- [Documentation](DOCUMENTATION.md)

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
