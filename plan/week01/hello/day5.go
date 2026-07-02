package main

import "fmt"
import "errors"

type User struct {
	Name string
}

type Animal interface {
	Sound() string
}

type Dog struct {
	Name string
}

func (d Dog) Sound() string {
	return "汪汪汪"
}

type Cat struct {
	Name string
}

func (c Cat) Sound() string {
	return "喵喵喵"
}

func describe(a Animal) {
	fmt.Println(a.Sound())
}

func main() {
	fmt.Println("Hello, World!")
	d := Dog{Name: "旺财"}
	c := Cat{Name: "咪咪"}
	describe(d)
	describe(c)

	// 测试错误处理
	user, err := findUser(-1)
	if err != nil {
		fmt.Println(err)
	} else {
		fmt.Println(user.Name)
	}

}
	var ErrNotFound = errors.New("not found")

func findUser(id int) (*User, error) {
	if id <= 0 {
		return nil, ErrNotFound
	}
	return &User{Name: "张三"}, nil
}
