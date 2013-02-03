
OpenTasks API
===


**Response Content Type:** application/json


# Tasks


## Get tasks

**Get all tasks**

	GET /tasks

**Get single task**

	GET /tasks/:id


### Response

	{
		"id": "269",
		"date": "0000-00-00 00:00:00",
		"due_date": "0000-00-00 00:00:00",
		"user": "0",
		"title": "This is a title",
		"status": "complete",
		"priority": "2",
		"type": "task",
		"parent": "0"
	}


## Insert/update task

### New task

	POST /tasks 

**Allowed Parameters**

- `title` - *Optional* String
- `due_date` - *Optional* DATETIME `0000-00-00 00:00:00`
- `status` - *Optional* String
- `parent` - *Optional* Integer

**Update task**

	POST /tasks 
	
or

	POST /tasks/:id

**Allowed Parameters**

- `id` - *Required* Integer (via URI or POST parameter)
- `title` - *Optional* String
- `due_date` - *Optional* DATETIME `0000-00-00 00:00:00`
- `status` - *Optional* String
- `parent` - *Optional* Integer


