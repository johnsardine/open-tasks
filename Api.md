OpenTasks API
===

**Response Content Type:** application/json


# Tasks

**Predefined Fields**

* `id`
* `date`
* `due_date`
* `title`
* `status`
* `priority`
* `parent`

## Get tasks

**Sample**

	/tasks/?parameter=value&:parameter=:value,:value:,:value

In case a requested parameter is not predefined, will search in meta for any tasks that contain a meta field with `key = :parameter` and its value is `:value`

### Get all tasks

	GET /tasks
	
**Sample response**

	[
	    {
	        "id": "1",
	        "date": "0000-00-00 00:00:00",
	        "due_date": "0000-00-00 00:00:00",
	        "user": "0",
	        "title": "Title",
	        "status": "",
	        "priority": "0",
	        "type": "task",
	        "parent": "0",
	        "foo": "restaurant"
	    },
	    {
	        "id": "2",
	        "date": "0000-00-00 00:00:00",
	        "due_date": "0000-00-00 00:00:00",
	        "user": "0",
	        "title": "Title",
	        "status": "",
	        "priority": "0",
	        "type": "task",
	        "parent": "0",
	        "foo": "bar"
	    }
	]

### Get single task

	GET /tasks/:id

or

	GET /tasks/?id=:id


**Sample response**

	{
		"id": "1",
		"date": "0000-00-00 00:00:00",
		"due_date": "0000-00-00 00:00:00",
		"user": "0",
		"title": "Title",
		"status": "pending",
		"priority": "1",
		"type": "task",
		"parent": "0",
		"foo": "bar"
	}

If a task is requested using an `id` and only one is passed, a single object will be returned instead of an array


## Insert/update task

### New task

	POST /tasks 

All parameters are optional, but at least one must be provided

### Update task

	POST /tasks 
	
or

	POST /tasks/:id

**Parameters**

- `id` - *Required* Integer (via URI or POST parameter)

Send any parameters that need to be updated

**Sample**

	POST /tasks/1?title=New+title

If a meta field is sent that does not exist, it will be created

**Sample**

	POST /tasks/1?bar=foo
	
Task 1 has no meta `bar`, so it will be created and assigned the value `foo`

**Response**

Will return an object with the full affected item