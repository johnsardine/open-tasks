Open Tasks
==========

Trying to set a standard for task lists to allow interchangeabillity between apps

##Structure

**Main table**

* `id` - Auto-increment unique field
* `start_date` 
* `due_date`
* `user`
* `title`
* `status`
* `type`
* `parent`

**Support table**

* `id` - Auto-increment unique field
* `related`
* `key`
* `value`


## API

* Require autentication (add public tasks/lists?)
* Allow creation, edition, deletion and sharing

## Problems:

* How to deal with accounts and autentication. Independently hosted? Self hosted?
* How to connect different apps

