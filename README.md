Who Contacted Me
================

This project is implemented in php using apache. The httpd.conf is incluses in 
the apache directory.

Authentication
--------------

In order to use the API you need an auth token. To get one you can goto 
http://server/oauth.php with a gui browser.I this case I stored them 

The token mentioned in the problem description is the part of the token called 
access_tokenx. The token itself is a json object with addtional fields. I 
decided that my API has access to the token object. Somehow, the tokens on up 
in the tokens directory, using /oauth.php or some other mechanism.

I did notice that I can, if I have a valid access_token, mock the token object.
I can fake it, basically.

RequestID
---------

You can get a request id by:

curl http://server/v1/emails/ -d "{ \"token\": \"ya29.zgGxntg-6JucThU36qbk0D1Sr53Iapjs4UM-pfyBHO6LeU7Jvn7V0watR_K_MnUpLZ0CBg\" }" 

Returns:

{"requestId":"user@email.com"}

Because this takes some time it would make sense to start a background process 
to do this, but I am not doing that.

I am sure there are many ways to solve this. Ther most straightforward one is 
to get all the messages and loop through them. I did that, then I decided to 
requesry after each set of processed messages and update the query to exclude 
the emails I have already found.

I haven't verified that the result set is correct. A way to do that would be to 
execute the query with all the senders in the exclude list. I should return an 
empty reult set.

I decided to use the user's email address as requestid. Having uniquely 
identified the user I could prevent multiple concurrent requests.

Emails
------

To retrieve the emails:

curl http://server/v1/emails/{user@email.com}

Returns:

{"total":208,"values": 
    ["support@github.com","noreply@github.com","icare7@amcustomercare.att-mail.com"]}
