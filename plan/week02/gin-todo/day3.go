package main

import (
	"net/http"

	"github.com/gin-gonic/gin"
)

type CreateTodoRequest struct {
	Title    string `json:"title" binding:"required,min=1,max=100"`
	Priority int    `json:"priority" binding:"required,gte=1,lte=100"`
}

func createTodo(c *gin.Context) {
	var req CreateTodoRequest
	if err := c.ShouldBindJSON(&req); err != nil {
		c.JSON(http.StatusBadRequest, gin.H{
			"error": err.Error(),
		})
		return
	}
	c.JSON(http.StatusOK, gin.H{
		"title":    req.Title,
		"priority": req.Priority,
	})
}
func main() {
	r := gin.Default()
	r.POST("/todos", createTodo)
	r.Run(":8080")
}
