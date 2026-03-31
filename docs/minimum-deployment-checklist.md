# Minimum Deployment Checklist

Use this checklist to get the first real server live with the fewest possible steps.

## 1. Create the first admin user

- Run `php artisan make:filament-user`
- Use your real admin email, not a demo account
- This user is the person who will sign in to the Filament panel and manage everything else

## 2. Set the app basics

- Confirm `.env` is correct for local, staging, or production use
- Use local MySQL for the database connection before the first deploy
- Set the app name, URL, database, queue, cache, and mail values
- Open the admin panel and review **App Settings**

## 3. Add the first real server

- Open **Servers**
- Click **Create**
- Choose the real connection type:
  - SSH key
  - password
  - local
  - cPanel
- Enter the real host, SSH port, and username
- Add credentials only for the connection type you actually use

## 4. Run the server checks

- Open the server record
- Use **Test Connection**
- Use **Provision server**
- If the server is cPanel, use **cPanel connection wizard** and confirm the API token and SSH port

## 5. Create the first site

- Open **Sites**
- Click **Create**
- Link the site to the server you just created
- Fill in:
  - repository URL for Git deploys, or
  - local source path for local deploys
- Confirm the branch, PHP version, deploy path, and web root

## 6. Configure the runtime

- Add the shared `.env` values you need for the app
- Add any shared files the app expects
- Use the preview panel to check the final runtime file before saving

## 7. Bootstrap the deploy path

- Open the site record
- Click **Bootstrap deploy path**
- For cPanel sites, use the site bootstrap wizard if needed
- Wait for the path to be created and verified

## 8. Deploy the app

- Open the site record
- Click **Deploy**
- Watch the terminal and deployment logs
- Confirm the release path switches correctly when the deploy finishes

## 9. Verify the app

- Open the deployed site in a browser
- Confirm the app loads
- Check the terminal, deployment timeline, and alerts inbox for errors

## 10. Turn on operational safety

- Enable health checks
- Confirm alert delivery settings
- Add webhook sync if you want GitHub push deploys
- Set up backups if the site needs restore protection

## Real Server Example

If your first target is `freshfromnaija.com`, use that as the real server host in the server setup step and keep the rest of the checklist the same.
