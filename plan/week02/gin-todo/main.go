package main

import "github.com/gin-gonic/gin"

func main() {
	// 带 Logger 和 Recovery 两个默认中间件
	r := gin.Default()
	r.GET("/ping", func(c *gin.Context) {
		c.JSON(200, gin.H{"message": "pong"})
	})
	r.Run(":8080")
}
