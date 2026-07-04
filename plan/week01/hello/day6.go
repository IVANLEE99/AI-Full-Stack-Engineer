package main

import (
	"encoding/json"
	"net/http"
	"sync"
)

// todo 数据结构
type Todo struct {
	ID    int    `json:"id"`
	Title string `json:"title"`
	Done  bool   `json:"done"`
}

// todo 全局变量
var (
	todos  = make(map[int]Todo)
	nextID = 1
	mu     sync.Mutex
)

// 创建+列表
func todoHandler(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	switch r.Method {
	case http.MethodGet:
		mu.Lock()
		list := make([]Todo, 0, len(todos))
		for _, todo := range todos {
			list = append(list, todo)
		}
		mu.Unlock()
		// TODO: 返回JSON格式的列表
		json.NewEncoder(w).Encode(list)

	case http.MethodPost:
		// TODO: 创建新todo
		var todo Todo
		if err := json.NewDecoder(r.Body).Decode(&todo); err != nil {
			http.Error(w, "Invalid request body", http.StatusBadRequest)
			return
		}
		mu.Lock()
		todo.ID = nextID
		nextID++
		todos[todo.ID] = todo
		mu.Unlock()
		w.WriteHeader(http.StatusCreated)
		json.NewEncoder(w).Encode(todo)

	case http.MethodPut:
		// TODO: 更新todo
		var todo Todo
		if err := json.NewDecoder(r.Body).Decode(&todo); err != nil {
			http.Error(w, "Invalid request body", http.StatusBadRequest)
			return
		}
		mu.Lock()
		todos[todo.ID] = todo
		mu.Unlock()
		json.NewEncoder(w).Encode(todo)

	case http.MethodDelete:
		// TODO: 删除todo
		var todo Todo
		if err := json.NewDecoder(r.Body).Decode(&todo); err != nil {
			http.Error(w, "Invalid request body", http.StatusBadRequest)
			return
		}
		mu.Lock()
		delete(todos, todo.ID)
		mu.Unlock()
		w.WriteHeader(http.StatusNoContent)

	default:
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
	}
}

func main() {
	http.HandleFunc("/todos", todoHandler)
	http.ListenAndServe(":8080", nil)
}
