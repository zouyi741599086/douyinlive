下载 protobuf：https://github.com/protocolbuffers/protobuf/releases
将下载的protobuf/bin 加入环境变量，命令行执行 protoc --sersion 看是否成功
当前目录执行命令：protoc --php_out=. douyin.proto
将当前目录生成的app/protobuf下douyin文件夹剪切到当前目录
将GPBMetadata/Douyin.php 的命名空间改为 namespace app\protobuf\GPBMetadata;
打开douyin文件夹下所有文件，将\GPBMetadata 替换为 \app\protobuf\GPBMetadata