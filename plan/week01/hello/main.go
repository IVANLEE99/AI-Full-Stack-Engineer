package main

import "fmt"

func main() {
	fmt.Println("Hello, Go!")
	// 变量声明
	var name string = "Go"
	fmt.Println(name)
	var age int = 18
	fmt.Println(age)
	count := 10
	fmt.Println(count)

	//零值
	var s string
	var n int
	var b bool
	var p *int
	fmt.Println(s, n, b, p)

	result, err := divide(10, 2)
	if err != nil {
		fmt.Println(err)
	} else {
		fmt.Println(result)
	}
}

func divide(a, b int) (int, error) {
	if b == 0 {
		return 0, fmt.Errorf("division by zero")
	}
	return a / b, nil
}
