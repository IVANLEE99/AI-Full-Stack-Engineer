package main

import (
	"fmt"
	"time"

	"github.com/gin-gonic/gin"
)

// 自定义日志中间件:统计耗时
func Logger() gin.HandlerFunc {
	return func(c *gin.Context) {
		start := time.Now()
		fmt.Println("进入中间件 ->")
		c.Next() //放行:继续执行后面的代码
		//c.Next()之后的代码,会在路由处理函数执行完毕后执行
		fmt.Printf("<- 离开中间件,耗时 %v\n", time.Since(start))
	}
}

// 权限验证中间件
func Auth() gin.HandlerFunc {
	return func(c *gin.Context) {
		token := c.GetHeader("Authorization")
		fmt.Println("权限验证中间件 ->", token)
		if token == "" {
			c.JSON(401, gin.H{"error": "Unauthorized"})
			c.Abort()
			return
		}
		//TODO: 权限验证逻辑
		c.Next()
	}
}

func main() {
	r := gin.Default()

	//全局中间件
	r.Use(Logger()) //全局中间件

	r.GET("/public", func(c *gin.Context) {
		c.JSON(200, gin.H{"message": "Public"})
	})

	//需要权限验证的路由
	r.GET("/private", Auth(), func(c *gin.Context) {
		c.JSON(200, gin.H{"message": "Private"})
	})

	r.Run(":8080")
}
