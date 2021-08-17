# AdMob TgBot
 The AdMob-TgBot allows publishers to get information about their AdMob account by using their own telegram bot.

## Requirements ##
- [PHP 5.6.0 or higher](https://www.php.net/)

## Setup
To get started quickly, follow these steps.

- **Step 1:** Upload the project in your server.
1. Download the source code from [Releases](https://github.com/Aghajari/AdMob-TgBot/releases).
2. Uncompress the zip file you download in your server. The path must be something like `/path/to/AdMob/...`

- **Step 2:** Enable the AdMob Api.
1. Visit https://console.developers.google.com to register your application.
2. From the [API Library](https://console.cloud.google.com/start/api?id=admob.googleapis.com), enable
   the **AdMob API**.
3. Click on **APIs & Services > Credentials** in the left navigation menu.
4. Click **CREATE CREDENTIALS > OAuth client ID**.
5. Select **Web application** as the application type, give it a name, then click
   **Create**.
6. From the Credentials page, Save Client ID And Client secret somewhere. (you need these in 4th step!)
7. Add `/path/to/AdMob/Auth.php` to Authorized redirect URIs. (Link of `Auth.php` in your server)

- **Step 3:** Create a Telegram Bot.
1. Create a telegram bot using botfather ([See more](https://core.telegram.org/bots#6-botfather))
2. Save the **token** somewhere. (you need these in next step!)
3. Set the bot Webhook `https://api.telegram.org/bot<YOUR_TOKEN>/setWebhook?url=<BOT_LINK>` <br> Replace `<YOUR_TOKEN>` with the bot token and replace `<BOT_LINK>` with `/path/to/AdMob/Bot.php`. (Link of `Bot.php` in your server)

- **Step 4:** Set AdMobOptions values.
1. Open `/path/to/AdMob/AdMobOptions.php` in your server.
2. Replace `<TELEGRAM_TOKEN>` with your bot token that you create in step 3.
3. Replace `<YOUR_PUBLICATION_ID>` with your AdMob publication ID.
4. Replace `<YOUR_CLIENT_ID>` And `<YOUR_CLIENT_SECRET>` with values which you saved in step 2.
5. Replace `<YOUR_REDIRECT_URI>` with `/path/to/AdMob/Auth.php`. (Link of `Auth.php` in your server)
6. Replace `<YOUR_TELEGRAM_USER_ID>` with your userID to be admin of the bot. (You can use [`@userinfobot`](https://github.com/nadam/userinfobot) to find your userID)

- **Step 5:** Login to the panel by the bot. (Only required once)
1. Start the bot (With the admin account)
2. Send `/token`
3. Click on Login (Will open oauth link)
4. Login to your google account (the one which you enabled the AdMob Api)
5. If you logged in successfully, The bot will send the new token to you and then everything is ready.

**Done!**

## Defining Subusers
Admins can use the bot to add/remove subusers.

1. Start the bot (With an admin account)
2. Send `/addUser`
3. Send the subuser userID
4. Send `/addAppToUser`
5. Select the subuser
6. Send the unique ID of the application that subuser can report (must be something like `ca-app-pub-1234~1234`)

You can also yourself as a subuser and restrict the apps that you can report.

## Reports
Reports contain these informations:

- **🆔 :** The unique ID of AdUnit or application.
- **💰 :** The estimated earnings.
- **eCPM:** The estimated earnings per thousand ad impressions.
- **Clicks:** The number of times a user clicks an ad.
- **Requests:** The number of ad requests.
- **MatchedRequests:** The number of times ads are returned in response to a request.
- **Impr:** The total number of ads shown to users.
- **Impr. CTR:** The ratio of clicks over impressions.
- **ShowRate:** The ratio of ads that are displayed over ads that are returned, defined as impressions / matched requests.
- **MatchRate:** The ratio of matched ad requests over the total ad requests.

## Commands

- **`/admob` :** Will report total status of panel (Only restricted apps for subusers) order by Today, Yesterday, This month and last month.
- **`/report` (DATE optional) :** Reports all applications separately.
- **`/reportByApp` (DATE optional) :** Reports a specific application.
- **`/reportByAdUnit` (DATE optional) :** Reports a specific application based on the AdUnits.
- **`/reportByCountry` (DATE optional) :** Reports a specific application based on the Country.

*DATE optional* means you can set start and end date of the report. for example:
- /report Today (Or Yesterday)
- /report Last month (Or Year)
- /report This month (Or Year)
- /report 7days (X days or months or years)
- /report 2021/8/17
- /report 2021/2/4 to 2021/5/7
