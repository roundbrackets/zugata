Who Contacted Me
================

I created and used my own credentials. I couldn't figure out how to use the 
provided ones.

The code is live at zugata.gunn.so.

Authentication
--------------

Get a token: http://zugata.gunn.so:81/oauth.php

Well, if you want to, a, it's not encrypted and b, I can read your emails.

You can get a request id by:

    curl http://zugata.gunn.so:81/v1/emails/ -d "{ \"token\": \"TOKEN\" }" 

or 

    ./bin/requestid.sh TOKEN

Returns:

    {"requestId":"user@email.com"}

Then get the emails:

    curl http://zugata.gunn.so:81/v1/emails/{user@email.com}

or 

    ./bin/emails.sh user@email.com

If you're curious you can: 

    curl http://zugata.gunn.so:81/v1/emails/{queen@roundbrackets.com}

I'm sure there is nothing embarrassing in there.

Returns:

{"total":208,"values": 
    ["support@github.com","noreply@github.com","icare7@amcustomercare.att-mail.com"]}



