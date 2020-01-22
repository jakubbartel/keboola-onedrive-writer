# Keboola OneDrive Writer

Writes data to OneDrive/SharePoint

## Microsoft Graph API Application

1. Go to <https://portal.azure.com/> (registration required) and section *App registrations*
2. Click "New registration" button, fill in name of the new Application. Application id will be generated
3. Setup Authentication: go to section *Authentication*. Redirect URIs : fill in *Web* *Redirect URI*. Fill in Logout URL. Implicit grant check *Access tokens* and *ID tokens*. Check *Accounts in any organizational directory*.
4. Generate application secret in section *Certificates & secrets* button "New client secret" with "Never" expiration.
5. Add *API permissions*: Microsoft Graph API - Delegated permissions that oAuth can ask for: `Files.ReadWrite.All`, `Sites.ReadWrite.All`
6. Fill other fields at your will
7. Hit "Save"

## OAuth

Use *Application Id*, *Secret* and *redict url* for OAuth with following links:

- oauth url: `https://login.microsoftonline.com/common/oauth2/v2.0/authorize?state=__state__&scope=offline_access%20Files.Read%20Files.ReadWrite%20Sites.Read.All%20Sites.ReadWrite.All&response_type=code&approval_prompt=auto&redirect_uri=__redirect_uri__&client_id=__client_id__`
- token url: `https://login.microsoftonline.com/common/oauth2/v2.0/token`

## OAuth testing

Directory `/oauth` contains docker-compose file to run local OAuth testing environment - printing all OAuth data
returned from the server in browser.

1. `cd oauth`
2. `cp .env.example .env` open `.env` and fill all variables
3. `docker-compose up`
4. add redirect url `https://localhost:10200` to Microsoft Graph API Application settings
5. open web browser `https://localhost:10200` which should be redirected to Microsoft Login or print acquired access token
6. use `https://localhost:10200?refresh` to manually refresh actual access token
