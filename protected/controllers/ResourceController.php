<?php
    class ResourceController extends Controller
    {
        
        public function actionIndex()
        {
            $model = new Resource;
            $tags = Tags::model()->findAll('status=:status', array('status' => 1));
            
            if(isset($_POST['Resource'])) {
                if(!isset(Yii::app()->user->identity)) {
                    $this->errors['请在登录状态下分享'] = false;
                    $this->render('index', array('tags' => $tags, 'model'=>$model, 'errors' => $this->errors));
                    return;
                }
                
                if($_FILES['Resource']['error']['attachment'] === 4 && empty($_POST['Resource']['remote_resource'])) {
                    $this->errors['附件和外部链接必须至少要填一项'] = false;
                } 
                if($_FILES['Resource']['error']['attachment'] != 4) {
                    $attachment = CUploadedFile::getInstance($model, 'attachment');
                    if($attachment->getError() != 0) {
                        $this->errors['上传文件错误'] = false;
                    }
                    if($attachment->getSize() > 1024*1024*5) {
                        $this->errors['文件大小不能超过5M']= false;
                    }
                    $allow_type = array('jpg', 'jepg', 'bmp', 'gif', 'zip', 'png', 'doc', 'wps', 'xls', 'et', 'txt', 'pdf');
                    if(!in_array($attachment->getExtensionName(), $allow_type)) {
                        $this->errors['文件类型不允许'] = false;
                    }
                    $attachment_url = date('Ym').'/'.md5(time()).'.'.$attachment->getExtensionName();
                }
                if(empty($this->errors)) {
                    //如果上面的步骤都没有问题然后才保存文件，记录到数据库
                    $model->attributes=$_POST['Resource'];
                    $model->contributor = Yii::app()->user->name;
                    $model->attachment = isset($attachment_url) ? $attachment_url : '';  
                    $model->create_time = date('Y-m-d H:i:s');
                    
                    
                    $model->validate();
                    if($aa = $model->save()) {
                        // 查询用户搜索的关键词集合，更新缓存
                        $redis = RedisStorage::getInstance();
                        $keys = $redis->sMembers(RESOURCE_SEARCH_KEY_SETS);
                        $resource_ser = serialize(Resource::model()->findByPk($model->id));
        
                        foreach($keys as $key) {
                            if(stristr($model->title, $key)) {
                                $redis->lPush(RESOURCE_SEARCH_KEY_PREFIX.$key, $resource_ser);
                            }
                        }
                        
                        //数据记录成功后转储附件
                        if(isset($attachment_url) && is_object($attachment) && get_class($attachment)==='CUploadedFile') {
                            if(!file_exists(ATT_URL.date('Ym'))) {
                                mkdir(ATT_URL.date('Ym'), true);
                            }
                            if(!file_exists(ATT_URL.date('Ym').'/index.php')) {
                                file_put_contents(ATT_URL.date('Ym').'/index.php', '<?php header("Location: http://share.hgdonline.net");');
                            }
                            $attachment->saveAs(ATT_URL.$model->attachment, true);//附件存储成功
                        }
                        $this->errors['分享成功，谢谢您的贡献！<br/>点击查看<a href = "'.$this->createUrl('resource/single', array('rid'=> $model->id)).'">'.$this->createUrl('resource/single', array('rid'=> $model->id)).'</a>'] = true;
                    } else {
                        $this->errors += $this->assembleErrors($model->getErrors());
                    }
                }
            }
            $this->render('index', array('tags' => $tags, 'model'=>$model, 'errors' => $this->errors));
        }

        /**
        * This is the action to handle external exceptions.
        */
        public function actionSingle() {
            if(!isset($_GET['rid'])) {
                $this->redirect(array('index/index'));
            }
            //评论
            $resource = Resource::model()->findByPk($_GET['rid']);   
            if(isset(Yii::app()->session['notice'])) {
                $notices = Yii::app()->session['notice'];
                foreach($notices as $notice) {
                    if($notice->resource_id == $resource->id) {
                        Comment::model()->updateByPk($notice->id, array('status' => 1));
                        unset(Yii::app()->session['notice']);
                        unset(Yii::app()->session['notice_num']);
                        $this->getNotice();
                    }
                }
            }
            
            if(isset($_POST['content'])) {
                if(!isset(Yii::app()->user->identity)) {
                    $this->errors['请在登录状态下评论！'] = false;
                } else {
                    $comment = new Comment();
                    $comment->content = $_POST['content'];
                    $comment->author = Yii::app()->user->name;
                    $comment->comment_to = !empty($_POST['comment_to']) ? $_POST['comment_to'] : $resource['contributor'];
                    $comment->resource_id = $_GET['rid'];
                    $comment->create_time = date('Y-m-d H:i:s');
                    $comment->validate();
                    if(!$comment->save()) {
                        $this->errors += $this->assembleErrors($comment ->getErrors());
                    }
                }
            }
            
            $tag_name = Tags::model()->findByPk($resource->tag_id)->name;
            
            //查找评论
            $total_records = Comment::model()->countByAttributes(array('resource_id' => $_GET['rid']));
            if(!isset($_GET['page'])) {
                $_GET['page'] = 1;
            } 
            $page_info = $this->paging($total_records, $_GET['page'], 12, 8);  //默认最多显示12页，每页8条记录
            $total_page = $page_info['total_page'];
            $cur_page = $page_info['cur_page'];
            $comments = Comment::model()->findAll(array(
                'condition' => 'resource_id=:resource_id',
                'params' => array(':resource_id' =>$_GET['rid'] ),
                'order' => 'create_time desc',
                'limit' => 8,
                'offset' => ($cur_page - 1)*8,
            ));
           
            $this->render('single', array(
                    'resource'=> $resource,
                    'tag_name' => $tag_name,
                    'errors' => $this->errors,
                    'comments' => $comments,
                    'total_page' => $total_page,
                    'cur_page' => $cur_page,
                    'link_argument'  => array('rid' => $_GET['rid']),
                )
            );
        }

        public function actionSearch() {
            if(!empty($_POST['key']) || !empty($_GET['key'])) {
                $key = !empty($_POST['key']) ? $_POST['key'] : $_GET['key'];
                $lkey = RESOURCE_SEARCH_KEY_PREFIX.$key;
                $redis = RedisStorage::getInstance();
                
                if($redis->exists($lkey)) {
                    $resources_ser = $redis->lRange($lkey, 0, -1);
                    foreach($resources_ser as $val) {
                        $resources[] = unserialize($val);
                    }
                } else {
                    $resources = Resource::model()->findAllBySql("select * from resource where title like :title limit 50",array(':title' => '%'.$key.'%'));
                    if(!empty($resources)) {
                        foreach($resources as $resource) {
                            $redis->lpush($lkey, serialize($resource));
                        }
                        $redis->sAdd(RESOURCE_SEARCH_KEY_SETS, $key);
                    }
                }
                
                $total_records = sizeof($resources);  // max total records is 50
                $total_page = (int)ceil($total_records/10);
                if(!isset($_GET['page'])) {
                    $cur_page = 1;
                } else {
                    if($_GET['page'] > $total_page) {
                        $cur_page = $total_page;
                    } else if($_GET['page'] < 1) {
                        $cur_page = 1;
                    } else {
                        $cur_page = $_GET['page'];
                    }
                }
                
                if(!isset($_GET['mobile'])) {
                    $result = array_slice($resources, ($cur_page - 1)*8, 10);
                } else {
                    foreach($resources as $key => $resource) {
                        $result[$key]['id'] = $resource->id;
                        $result[$key]['title'] = $resource->title;
                        $result[$key]['description'] = $resource->description;
                    }
                    echo json_encode($result);
                    exit;
                }
                
                $this->render('search', array(
                    'resources' => $result,
                    'key' => $key,
                    'total_records' => $total_records,
                    'total_page' =>$total_page, 
                    'cur_page' => $cur_page,
                ));
            } else {
                $this->redirect(array('index/index'));
            }

        }

        public function actionBytag() {
            if(!isset($_GET['tid'])) {
                $this->redirect(array('index/index'));
            }
            
            $total_records = Resource::model()->count('tag_id=:tag_id', array('tag_id' => $_GET['tid']));
            if(!isset($_GET['page'])) {
                $_GET['page'] = 1;
            }
            $page_info = $this->paging($total_records, $_GET['page'], 0, 10);
            $total_page = $page_info['total_page'];
            $cur_page = $page_info['cur_page'];
            $resources = Resource::model()->findAll('tag_id=:tag_id', array(':tag_id' => $_GET['tid']),array('order' => 'create_time desc', 'limit' => 18, 'offset' => ($cur_page - 1)*18 ));
            
            $tag_name = Tags::model()->findByPk($_GET['tid'])->name;
            $this->render('bytag', array(
                'resources' => $resources,
                'tag_name' => $tag_name,
                'total_page' => $total_page,
                'cur_page' => $cur_page,
                'total_records' => $total_records,
               'link_argument' => array('tid' => $_GET['tid']),
            ));
            
            
        }

        public function actionDownload() {
            if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
                echo json_encode(array('status' => 400, 'mes' => 'id error.'));
                exit;
            }
            if(empty($_GET['email'])) {
                echo json_encode(array('status' => 401, 'mes' => 'invalid user email.'));
                exit;
            }
            
            $system = isset($_GET['system']) ? $_GET['system'] : '';
            $id = (int)$_GET['id'];
            $resource = Resource::model()->findByPk($id);
            $title = $resource->title;
            $description = $resource->description;
            $contributor = $resource->contributor;
            $download_url = $resource->attachment ? ATT_DOWN_URL.$resource->attachment : $resource->remote_resource;
            $create_time = $resource->create_time;
            $detail_link = SITE_URL."index.php?r=resource/single&rid=$resource->id";
            $feedback_url = "http://share.hgdonline.net/index.php?r=feedback/index";
            $site_url = SITE_URL;
            require_once ASSETS_DIR.'template/reply_download.email.php';
            
            $mail = Yii::createComponent('application.extensions.mailer.EMailer');
            $mail->IsSMTP();                                      // set mailer to use SMTP
            $mail->Host = EMAIL_SMTP_HOST;  // specify main and backup server
            $mail->SMTPAuth = true;     // turn on SMTP authentication
            $mail->Username = EMAIL_FROM;  // SMTP username
            $mail->Password = EMAIL_PASSWORD; // SMTP password
            $mail->From = EMAIL_FROM;
            $mail->FromName = EMAIL_FROM_NAME;
            $mail->AddAddress($_GET['email'], "收件人");                 // name is optional
            $mail->Subject = "来自湖工大分享网的回复";
            $mail->Body = $email;
            $mail->IsHTML(true);
            $result = $mail->Send();
            if($result) {
                echo json_encode(array('status' => 200, 'mes' => 'seccuss'));
                exit;
            } else {
                echo json_encode(array('status' => 402, 'mes' => 'send email error.'));
            }
        }

        public function actionError()
        {
            if($error=Yii::app()->errorHandler->error)
            {
                if(Yii::app()->request->isAjaxRequest)
                    echo $error['message'];
                else
                    $this->render('error', $error);
            }
        }
        
    }