package main

import "fmt"

type User struct {
	ID    int
	Name  string
	Email string
}

// Value receiver - cannot modify the original struct
func (u User) Greet() string {
	return "Hello, I am " + u.Name
}

// Pointer receiver - can modify the original struct
func (u *User) Rename(name string) {
	u.Name = name
}

func main() {
	user := User{ID: 1, Name: "John", Email: "john@example.com"}
	fmt.Println(user.Greet())
	user.Rename("Jane")
	fmt.Println(user.Greet())
}
