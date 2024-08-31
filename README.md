Cloudflare to MainWP Bridge

## main-wp-to-cloudflare-bridge-extension
This is installed on the MainWP Dashboard, this is now fully hooked in to report generation and the only modification needed is to add your API Key to the dashboard and it will add your new tokens for you; 
## new-template.php
This is a custom template based on the example uploaded to the Admin Bar Facebook group that set this in video and code solution https://www.youtube.com/watch?v=ytUNgJMu0vg

I recreated it just because I could and to begin to show what is possible, this uses new functionality which will be in a future update of the pro reports that was passed to me by MainWP support.

Which allows a custom function to create a new tag manipulating data and created the [website.updated.total]  template token


![Email gif][https://i.ibb.co/ZxMKt57/Kyles-email-template.gif]

Which allows a custom function to create a new tag manipulating data and created the [website.updated.total]  template token this function lives in my mainwp settings plugin but  I have added it below for you;

```
function mycustom_mainwp_pro_reports_addition_custom_tokens( $tokens, $site_id, $data ) {
    if(is_array($data) && isset($data[$site_id])){
         $total = 0;
         $total += isset($data[$site_id]['other_tokens_data']['body']['[plugin.updated.count]']) ? intval( $data[$site_id]['other_tokens_data']['body']['[plugin.updated.count]'] ) : 0;
         $total += isset($data[$site_id]['other_tokens_data']['body']['[theme.updated.count]']) ? intval( $data[$site_id]['other_tokens_data']['body']['[theme.updated.count]'] ) : 0;
         $total += isset($data[$site_id]['other_tokens_data']['body']['[wordpress.updated.count]']) ? intval( $data[$site_id]['other_tokens_data']['body']['[wordpress.updated.count]'] ) : 0;
         
         $tokens['[website.updated.total]'] = $total;
    }
    
    return $tokens;
}
```


## Usage Instructions
1) Add the main extension to your dashboard
   
2) Navigate to the extension within MainWP and click Cloudflare bridge so you can add your Cloudflare api key 
   
 ![Installed Extention][https://i.ibb.co/NKF2tgZ/Extentions-Installed.png]
 3) head over to Cloudflare and your API settings should look like this to work with this extension
    ![Cloudflare Token Settings][https://i.ibb.co/SNfYfLR/Cloudflare-API-Token.png]
 
 4) Now head back to your screen within the extension to add your API key which you'll need to get from Cloudflare in the step above and save
![Cloudflare API Settings](https://i.ibb.co/QQ489rC/set-cloudflare-api-token.png)
5) That's it you now have the new custom tokens avaliable to you within pro-reports
   ![New Tokens](https://i.ibb.co/J58WFZB/Cloudflare-new-custom-tokens-In-action2.png)
   



### What does it output?
It adds three new custom tokens you can access which are displayed below;
[cfmwp-requests]
[cfmwp-uniques]
[cfmwp-cached]
[cfmwp-bandwidth]*
[cfmwp-attacks]
![Example Output in a report](https://i.ibb.co/4TW9X26/Cloudflare-new-custom-tokens.png)
#### *bandwidth is reported from cloudflares api in bytes which isn't useful when you get to GB or TB so a new function was added to convert this to the "best unit" to report in code below
```
function cfmwp_format_bandwidth($bytes) {
    $units = array('bytes', 'KB', 'MB', 'GB', 'TB');
    $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
    $value = $bytes / pow(1024, $power);
    return round($value, 2) . ' ' . $units[$power];
}
```

**Please Note: I have tested this and written this and it works for my custom dashboard and requirements you will need to test in your setup**
