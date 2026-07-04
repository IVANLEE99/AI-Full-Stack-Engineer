package main

import "github.com/gin-gonic/gin"

func main() {
	// 带 Logger 和 Recovery 两个默认中间件
	r := gin.Default()
	r.GET("/ping", func(c *gin.Context) {
		c.JSON(200, gin.H{"message": "pong"})
	})

	api := r.Group("/api/v1")
	{
		api.GET("/todos", listTodos)
		api.GET("/todos/:id", getTodo)
	}
	r.Run(":8080")
}

func listTodos(c *gin.Context) {
	// 查询参数 :/todos?page=2
	page := c.DefaultQuery("page", "1")
	c.JSON(200, gin.H{"page": page})
}

func getTodo(c *gin.Context) {
	// 查询参数 :/todos/1
	id := c.Param("id")
	c.JSON(200, gin.H{"id": id})
}
