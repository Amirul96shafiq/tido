# Google Drive Integration Setup Guide

This guide documents how to create a Google Cloud project, authorize a service account, and configure tido to pull receipts from a Google Drive folder.

---

## Step 1: Create a Google Cloud Project & Enable APIs
1. Go to the [Google Cloud Console](https://console.cloud.google.com/).
2. Create a new project (e.g. `tido`).
3. Search for the **Google Drive API** and click **Enable**.

---

## Step 2: Create a Service Account
1. In the console, go to **APIs & Services > Credentials**.
2. Click **Create Credentials > Service Account**.
3. Fill in the details (e.g. name `tido-service-account`).
4. Skip the permissions steps and click **Done**.
5. Select the newly created Service Account, go to the **Keys** tab, and click **Add Key > Create new key**.
6. Choose **JSON** format and click **Create**. Save this file securely.

---

## Step 3: Share the Google Drive Folder
1. Create a folder in your Google Drive (e.g. `tido Receipts`).
2. Open the service account JSON key file you downloaded and find the `"client_email"` key (e.g., `tido-service-account@project.iam.gserviceaccount.com`).
3. Share your Google Drive folder with this email address, giving it **Editor** permissions.

---

## Step 4: Configure tido Environment
Open your `.env` file and populate the Google Drive keys:

```env
GOOGLE_DRIVE_CLIENT_ID=your-service-account-client-id
GOOGLE_DRIVE_CLIENT_SECRET=your-service-account-private-key-id
GOOGLE_DRIVE_REFRESH_TOKEN=your-service-account-private-key (multiline value, replace newlines with \n)
GOOGLE_DRIVE_FOLDER_ID=your-shared-folder-id-from-url
```

*Note: The Folder ID can be copied from the URL of your Google Drive folder (it is the long string of characters at the end of the URL).*
