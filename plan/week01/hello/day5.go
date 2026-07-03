package main

import (
	"fmt"
	"errors"
	"os"
	"sync"
	"time"
)

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

	// Defer 示例
	deferExample()
	fileExample()
	panicRecoverExample()
	multipleDefersExample()
	deferTimingExample()
	deferPerformanceExample()
	deferMutexExample()
	deferNestedExample()
}
	var ErrNotFound = errors.New("not found")

func findUser(id int) (*User, error) {
	if id <= 0 {
		return nil, ErrNotFound
	}
	return &User{Name: "张三"}, nil
}

// defer 基本示例
func deferExample() {
	fmt.Println("=== Defer 基本示例 ===")

	// defer 语句会在函数返回前执行
	fmt.Println("开始执行")

	// 立即执行的语句
	fmt.Println("执行中...")

	// defer 语句 - 会在函数返回前执行
	defer fmt.Println("defer 语句执行")

	fmt.Println("函数执行完毕")
	fmt.Println("输出顺序：开始执行 -> 执行中... -> 函数执行完毕 -> defer 语句执行")
}

// 文件操作中的 defer 使用
func fileExample() {
	fmt.Println("\n=== 文件操作中的 Defer 示例 ===")

	// 创建临时文件
	file, err := os.CreateTemp("", "example*.txt")
	if err != nil {
		fmt.Println("创建文件失败:", err)
		return
	}
	defer os.Remove(file.Name()) // 确保函数返回时删除临时文件
	defer file.Close()           // 确保函数返回时关闭文件

	// 写入内容
	content := []byte("Hello, Defer!")
	_, err = file.Write(content)
	if err != nil {
		fmt.Println("写入文件失败:", err)
		return
	}

	fmt.Println("文件写入成功")
	fmt.Println("文件将自动删除和关闭")
}

// defer 在 panic 和 recover 中的使用
func panicRecoverExample() {
	fmt.Println("\n=== Panic 和 Recover 示例 ===")

	func() {
		defer func() {
			if r := recover(); r != nil {
				fmt.Println("从 panic 中恢复:", r)
			}
		}()

		fmt.Println("准备 panic")
		panic("发生了一个错误")
	}()

	fmt.Println("程序继续执行")
}

// 多个 defer 的执行顺序
func multipleDefersExample() {
	fmt.Println("\n=== 多个 Defer 执行顺序示例 ===")

	fmt.Println("开始执行")

	defer fmt.Println("第一个 defer")
	defer fmt.Println("第二个 defer")
	defer fmt.Println("第三个 defer")

	fmt.Println("函数执行完毕")
	fmt.Println("执行顺序：后进先出 (LIFO)")
}

// defer 变量求值时机示例
func deferTimingExample() {
	fmt.Println("\n=== Defer 变量求值时机示例 ===")

	// 普通 defer：参数在 defer 语句处立即求值
	x := 1
	defer fmt.Println("普通 defer, x =", x) // 输出 1，不是 2
	x = 2
	fmt.Println("当前 x =", x)

	// 匿名函数 defer：变量在函数返回时求值
	y := 1
	defer func() {
		fmt.Println("匿名函数 defer, y =", y) // 输出 2
	}()
	y = 2
	fmt.Println("当前 y =", y)
}

// 实际应用：使用 defer 进行性能测量
func deferPerformanceExample() {
	fmt.Println("\n=== Defer 性能测量示例 ===")

	// 记录函数执行时间
	start := time.Now()
	defer func() {
		fmt.Printf("函数执行时间: %v\n", time.Since(start))
	}()

	// 模拟一些耗时操作
	for i := 0; i < 1000000; i++ {
		_ = i
	}
}

// defer 在互斥锁中的应用
func deferMutexExample() {
	fmt.Println("\n=== Defer 互斥锁示例 ===")

	var mu sync.Mutex
	var counter int

	// 使用 defer 确保锁一定会被释放
	increment := func() {
		mu.Lock()
		defer mu.Unlock() // 确保函数返回时释放锁

		counter++
		fmt.Printf("计数器: %d\n", counter)
	}

	// 并发执行
	for i := 0; i < 3; i++ {
		go increment()
	}

	// 等待足够时间让所有 goroutine 执行
	time.Sleep(100 * time.Millisecond)
}

// defer 在 defer 中的嵌套使用
func deferNestedExample() {
	fmt.Println("\n=== Defer 嵌套示例 ===")

	defer func() {
		fmt.Println("外层 defer 开始")
		defer func() {
			fmt.Println("内层 defer 执行")
		}()
		fmt.Println("外层 defer 结束")
	}()

	fmt.Println("主函数执行")
}
