Open Tasks
==========

Standard for task lists to allow interchangeabillity between apps

---

There are an infinite number of applications that people can use to manage tasks, projects, etc. Some are complex, other are simple, and may exist on a indefinite number of platforms.

The problem lies in the fact that, for example, the newly released [Clear](http://www.realmacsoftware.com/clear/), exists now, for both OSX and iOS, but an user that has a Mac and likes to use Clear to organize its tasks, cannot manage those tasks it in its Android device because there is no Clear app for Android, and this is a problem that happens with multiple applications. I mention Clear but i could mention, for example, Reminders (iOS and OSX), or any other web app that does not have a system app.

An user that likes how a certain app behaves, should not be limited by its platform support, and this is why this was created, to allow our stuff, to be platform agnostic and be managed by whatever app/service we prefer for whatever platform we use.

Below is my initial proposition, what I hope now is to have people that work with this type of applications, to contribute to this draft with their own input about what are the raw capabilities that are important to all (main table) and those more specific to a certain type of task/project manager(support table).

The goal is to have as much people as possible (directly connected to the issue or not) to contribute to the creation of a standard for the data structure this type of application.

---

##Structure

**Main table**

* `id` - Auto-increment unique field
* `start_date` 
* `due_date`
* `user` - this needs more thought
* `title` 
* `status` - `pending`, `completed`, etc.
* `order` or `priority` - used for ordering purposes (must be decided)
* `type` - Could be, for example, `task`, `group`, `note`, etc. (a common list of types must be defined)
* `parent` - `id` allows for hierarchical organization

**Support table**

* `id` - Auto-increment unique field
* `related` - `id` from main table
* `key` - allows for custom fields like `assigned_to`, which would contain user id. A list of common custom fields would need to be set so that apps with functionality that could be common to others with the same level of complexity, can use those fields.
* `value` - if it's an array, must be `JSON`


## API

* Require autentication (add public tasks/lists perhaps?)
* Allow creation, edition, deletion and sharing

## Problems:

* How to deal with accounts and autentication. The idea is that there is no main service, there is our data, but that presents problems regarding to account management and autentication.
* How to connect different apps/services. Like above, if there is no main service providing the data, how would it be acessed?

