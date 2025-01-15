## 方式1

php自建dockerhub反代；

1、添加站点，设置站点根目录为仓库的php目录。

2、nginx设置伪静态规则

```nginx
location / {
  try_files $uri /index.php?$query_string;
}
```

3、访问/testing，显示'docker ok!'代表站点部署成功。

4、修改 /etc/docker/daemon.json

`registry-mirrors` 节点增加或只保留你自己的服务地址
```json
{
  "registry-mirrors":["你自己的服务地址，例如：https://your-domain.com"]
}
```
然后拉取镜像测试是否生效。

## 方式2
参考bin目录