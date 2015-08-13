#!/bin/bash

curl http://zugata.gunn.so:81/v1/emails/ -d "{ \"token\": \"${1}\" }" 
