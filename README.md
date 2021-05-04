An example of integrating Intercom with Voximplant KIT using a custom channel
=============================================================================


Configure your credentials in [./env](.env) file:

* KIT_IM_API_URL: KIT Api url by your region. https://kit-im-eu.voximplant.com or https://kit-im-us.voximplant.com);
* KIT_ACCOUNT_NAME: Your account name in Voximplant KIT;
* KIT_API_TOKEN: Your api token in Voximplant KIT;
* KIT_CHANNEL_UUID: Your custom channel uuid in Voximplant KIT;
* INTERCOM_API_TOKEN: Api token for intercom Api;
* INTERCOM_ADMIN_ID: Admin id in intercom. Use for reply on message.


Run server:
```shell script
> php app.php
```


####  \* This solution is for example only, how to integrate intercom with voximplant KIT. Dont`t use it for production
