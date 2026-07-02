package main

import "fmt"

func main() {
	fmt.Println("Hello, World!")
	nums := []int{1, 2, 3, 4, 5}
	fmt.Println(nums) // [1 2 3 4 5]
	nums = append(nums, 6) // 在末尾添加元素
	fmt.Println(nums) // [1 2 3 4 5 6]

	//遍历
	for i, v := range nums {
		fmt.Println(i, v) // 0 1
		// 1 2
		// 2 3
		// 3 4
		// 4 5
		// 5 6
	}

	//切片
	sub := nums[1:3]
	fmt.Println(sub) // [2 3]

	//copy
	copy(sub, []int{7, 8})
	fmt.Println(sub) // [7 8]
	fmt.Println(nums) // [1 7 8 4 5 6]

	//删除
	nums = append(nums[:1], nums[2:]...)
	fmt.Println(nums) // [1 8 4 5 6]

	//map
	m := make(map[string]int)
	m["a"] = 1
	m["b"] = 2
	fmt.Println(m) // map[a:1 b:2]

	//初始化
	m2 := map[string]int{"a": 1, "b": 2}
	fmt.Println(m2) // map[a:1 b:2]

	//读取
	v, ok := m["a"]
	fmt.Println(v, ok) // 1 true

	//读取不存在的key
	v, ok = m["c"]
	fmt.Println(v, ok) // 0 false

	//nil map
	var m3 map[string]int
	fmt.Println(m3) // map[]

	//查找
	if v, ok := m["a"]; ok {
		fmt.Println(v) // 1
	}

	//长度
	fmt.Println(len(m)) // 2

	//删除
	delete(m, "a")
	fmt.Println(m) // map[b:2]


	//遍历
	for k, v := range m {
		fmt.Println(k, v) // b 2
	}

}
