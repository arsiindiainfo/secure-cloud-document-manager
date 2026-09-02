# processing-worker

§9.3's S3-event-triggered Lambda: thumbnail generation, basic metadata
extraction, and the documented virus-scan stub. See `index.js` for the
handler and the file-level comment for the full design rationale.

## Building the deployment package

`docker-compose.yml` mounts a single pre-built `processing-worker.zip`
(sibling to this folder) into the LocalStack container, rather than the raw
source directory. Building the zip *inside* the container by walking
`node_modules` through a Windows bind mount was measured at 10+ minutes for
a few dozen packages; building it natively on the host first and mounting
the single resulting file is close to instant.

To (re)build it after changing `index.js` or the dependencies:

```powershell
cd infrastructure/aws/lambda/processing-worker
npm install --omit=dev
Compress-Archive -Path * -DestinationPath ../processing-worker.zip -Force
```

`@aws-sdk/client-s3` is a `devDependency` only — it's listed so
`npm install` provides it for local editing/type-checking, but it is
**not** part of `node_modules` that gets zipped (see the `--omit=dev`
above). The Lambda Node.js runtime ships the AWS SDK v3 pre-installed
(real AWS does this too, precisely so functions don't need to bundle it
themselves), so `index.js`'s `require('@aws-sdk/client-s3')` resolves from
the runtime at invoke time, not from this package.
