# Tre Diary Web - PHP

[Tre Diary Web](https://github.com/SHxiaoleya/TreDiaryWeb)的适用于受限环境的纯 PHP 版本。可用于虚拟主机等场景。
---

### 演示站：[点击前往](https://tre-diary.rth1.xyz/)

---

## 项目特点
- 不依赖 `composer install`  
- 使用 `file_get_contents()` 读取 Markdown 文件  
- 支持 Front Matter（`title/date/weather`）  
- 支持搜索（标题 + 正文）  
- 支持基础 Markdown 渲染（零依赖）
- **纯 PHP 单文件可运行**（可按需拆分）
- **固定文件列表读取**（不依赖 `scandir`）
- **兼容特殊托管环境路径规则**
- **搜索、排序、展示完整流程内置**

---

## 目录示例

```txt
/
├── index.php
├── public/
│   └── style.css
└── diaries/
    ├── 1.md
    └── 2.md
```

---

## 路径规则

1. 读写文件使用明确路径字符串，如：
  `diaries/2026-04-28.md`
  `public/style.css`
2. 不要使用 __DIR__ 进行路径拼接（可能导致路径错误）
3. 不使用相对路径推断逻辑，尽量写清楚目标文件位置

---

## 配置方式

在`index.php`中维护文件数组：

```php
$DIARY_FILES = [
    "diaries/2026-04-28.md",
    "diaries/2026-04-29.md",
];
```

程序会按该列表逐个`file_get_contents()`读取，不扫描目录。

---

## 功能及其他配置

请前往[Tre Diary Web](https://github.com/SHxiaoleya/TreDiaryWeb)查看。

---

## License

本项目基于 MIT License 开源，你可以自由使用、修改和分发。