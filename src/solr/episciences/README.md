# solrConf

Solr Collection configuration files for Solr Cloud


## HOWTO
Upload new or updated conf files :

 `/opt/solr/bin/solr zk upconfig -d /source/dir -n configSetName`
 
 ## Examples
 On a node:
` cd /opt/solrconf`

Update episciences configset with configset named `episciences`:

` /opt/solr/bin/solr zk upconfig -d episciences -n episciences`

Use episciences configset with another configset name:

` /opt/solr/bin/solr zk upconfig -d episciences -n demo-episciences`


Conf files will then be visible in:  

`/opt/solr/server/solr/configsets`


Reload collection to see changes in solr

# Documentation

https://lucene.apache.org/solr/guide/solr-control-script-reference.html#upload-a-configuration-set
