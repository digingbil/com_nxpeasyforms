# Building Admin Assets

To regenerate the compiled admin JS/CSS bundle:

1. From the repository root run:

   ```bash
   cd media/com_nxpeasyforms
   npm install
   npm run build
   ```

   This writes updated files to `media/com_nxpeasyforms/js/admin.js`, `media/com_nxpeasyforms/css/admin.css`, and refreshes the Vite `manifest.json`.

2. Commit the updated manifest and generated assets as part of your packaging workflow.

The component loads assets via `AssetHelper::registerEntry()` which reads the Vite manifest, so no PHP changes are needed when new hashes are generated. The helper also falls back to the most recent `css/admin-*.css` file if the manifest is missing, but you should always run the build before releasing.
